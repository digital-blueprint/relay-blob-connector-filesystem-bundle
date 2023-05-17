<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\PoliciesStruct;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\BlobBundle\Helper\DenyAccessUnlessCheckSignature;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;
use Dbp\Relay\BlobBundle\Service\BlobService;

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

    /**
     * @var BlobService
     */
    private $blobService;

    public function __construct(ConfigurationService $configurationService, SluggerInterface $slugger, BlobService $blobService)
    {
        $this->configurationService = $configurationService;
        $this->slugger = $slugger;
        $this->blobService = $blobService;
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

        $payload = [
            'identifier' => $fileData->getIdentifier(),
            'validUntil' => $fileData->getExistsUntil()->format('c'),
            'path' => $destinationFilenameArray
        ];
        $contentUrl = $contentUrl = $this->configurationService->getLinkUrl().'blob/filesystem/'.$payload['identifier'].'?validUntil='.$payload['validUntil'].'&path='.$payload['path']['destination'].'/'.$payload['path']['filename'];

        $contentUrl = $contentUrl.'&checksum='.hash('sha256', $contentUrl.$fileData->getBucket()->getPublicKey());
        
        $fileData->setContentUrl($contentUrl);

        dump($fileData->getIdentifier());
        dump($fileData->getExistsUntil());

        //Upload file
        FileOperations::moveFile($fileData->getFile(), $destinationFilenameArray['destination'], $destinationFilenameArray['filename']);

        $this->blobService->saveFileData($fileData);
        $this->blobService->saveFile($fileData);
        dump($this->blobService->getFileDataByBucketIDAndPrefixWithPagination($fileData->getBucketID(), $fileData->getPrefix(), 1, 10));

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
        $payload = [
            'identifier' => $fileData->getIdentifier(),
            'validUntil' => $fileData->getExistsUntil()->format('c'),
            'path' => $destinationFilenameArray
        ];

        $contentUrl = '/blob/filesystem/'.$payload['identifier'].'?validUntil='.$payload['validUntil'].'&path='.$payload['path']['destination'].'/'.$payload['path']['filename'];

        $contentUrl = $contentUrl.'&checksum='.hash('sha256', $contentUrl.$fileData->getBucket()->getPublicKey());

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

    public function generateChecksum($fileData): ?string
    {
        try {
            $destinationFilenameArray = $this->generatePath($fileData);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Path could not be generated', 'blob-connector-filesystem:path-not-generated', ['message' => $e->getMessage()]);
        }

        $payload = [
            'identifier' => $fileData->getIdentifier(),
            'validUntil' => $fileData->getExistsUntil()->format('c'),
            'path' => $destinationFilenameArray
        ];

        $contentUrl = '/blob/filesystem/'.$payload['identifier'].'?validUntil='.$payload['validUntil'].'&path='.$payload['path']['destination'].'/'.$payload['path']['filename'];

        dump($contentUrl.$fileData->getBucket()->getPublicKey());

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
