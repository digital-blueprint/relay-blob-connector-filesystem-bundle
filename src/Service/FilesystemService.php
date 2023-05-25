<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\PoliciesStruct;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\BlobBundle\Helper\DenyAccessUnlessCheckSignature;
use Safe\DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

class FilesystemService implements DatasystemProviderServiceInterface
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var SluggerInterface
     */
    private $slugger;

    public function __construct(ConfigurationService $configurationService, SluggerInterface $slugger)
    {
        $this->configurationService = $configurationService;
        $this->slugger = $slugger;
    }

    /**
     * @throws \Exception
     */
    public function saveFile(FileData $fileData): ?FileData
    {
        try {
            $destinationFilenameArray = $this->generatePath($fileData);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Path could not be generated', 'blob-connector-filesystem:path-not-generated', ['message' => $e->getMessage()]);
        }

        // set content url
        $contentUrl = $contentUrl = $this->configurationService->getLinkUrl().'blob/filesystem/'.$fileData->getIdentifier().'?validUntil='.$fileData->getExistsUntil()->format('c').'&path='.$destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename'];
        $contentUrl = $contentUrl.'&checksum='.$this->generateChecksumFromFileData($fileData);
        $fileData->setContentUrl($contentUrl);

        //move file to correct destination
        FileOperations::moveFile($fileData->getFile(), $destinationFilenameArray['destination'], $destinationFilenameArray['filename']);

        return $fileData;
    }

    public function renameFile(FileData $fileData): ?FileData
    {
        // No action required here because it is only saved in blobBundle
        return $fileData;
    }

    /**
     * @throws \Exception
     */
    public function getLink(FileData $fileData, PoliciesStruct $policiesStruct): ?FileData
    {
        try {
            $destinationFilenameArray = $this->generatePath($fileData);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Path could not be generated', 'blob-connector-filesystem:path-not-generated', ['message' => $e->getMessage()]);
        }

        // set content url
        $contentUrl = '/blob/filesystem/'.$fileData->getIdentifier().'?validUntil='.$fileData->getExistsUntil()->format('c').'&path='.$destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename'];
        $contentUrl = $contentUrl.'&checksum='.$this->generateChecksumFromFileData($fileData);

        $fileData->setContentUrl($this->configurationService->getLinkUrl().substr($contentUrl, 1));

        return $fileData;
    }

    public function removeFile(FileData $fileData): bool
    {
        // Delete the file
        $destinationFilenameArray = $this->generatePath($fileData);
        $path = $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename'];

        FileOperations::removeFile($path, $destinationFilenameArray['destination']);

        return true;
    }

    public function generateChecksumFromFileData($fileData): ?string
    {
        try {
            $destinationFilenameArray = $this->generatePath($fileData);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Path could not be generated', 'blob-connector-filesystem:path-not-generated', ['message' => $e->getMessage()]);
        }

        // create url to hash
        $contentUrl = '/blob/filesystem/'.$fileData->getIdentifier().'?validUntil='.$fileData->getExistsUntil()->format('c').'&path='.$destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename'];

        // create sha256 hash
        $cs = hash('sha256', $contentUrl.$fileData->getBucket()->getPublicKey());

        return $cs;
    }

    private function generatePath(FileData $fileData): array
    {
        $id = $fileData->getIdentifier();
        $folder = substr($id, 0, 2);
        $safeFilename = $this->slugger->slug($id);
        $newFilename = $safeFilename.'.'.$fileData->getExtension();
        $destination = $this->configurationService->getPath();
        if (substr($destination, -1) !== '/') {
            $destination .= '/';
        }
        $destination .= $fileData->getBucket()->getPath();
        if (substr($destination, -1) !== '/') {
            $destination .= '/';
        }
        $destination .= $folder;

        return ['destination' => $destination, 'filename' => $newFilename];
    }

    private function generateContentUrl(string $id): string
    {
        $link = $this->configurationService->getLinkUrl();

        return $link.'blob/filesystem/'.$id;
    }

    private function generateContentUrlWithExpiry(string $id, string $validUntil): string
    {
        return $this->generateContentUrl($id).'&validUntil='.$validUntil;
    }

    private function generateSignedContentUrl(string $id, string $validUntil, string $signature): string
    {
        return $this->generateContentUrlWithExpiry($id, $validUntil).'&sig='.$signature;
    }
}
