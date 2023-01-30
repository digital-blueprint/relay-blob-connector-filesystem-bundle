<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\PoliciesStruct;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Dbp\Relay\BlobConnectorFilesystemBundle\Entity\ShareLinkPersistence;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\CoreBundle\Exception\ApiError;
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
     * @var ShareLinkPersistenceService
     */
    private $shareLinkPersistenceService;

    /**
     * @var SluggerInterface
     */
    private $slugger;

    public function __construct(ConfigurationService $configurationService, ShareLinkPersistenceService $shareLinkPersistenceService, SluggerInterface $slugger)
    {
        $this->configurationService = $configurationService;
        $this->shareLinkPersistenceService = $shareLinkPersistenceService;
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

        //Upload file
        FileOperations::moveFile($fileData->getFile(), $destinationFilenameArray['destination'], $destinationFilenameArray['filename']);

        //generate shareLink
        try {
            $shareLinkPersistence = $this->generateShareLink($fileData, $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename']);
        } catch (FileException $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Sharelink could not generated', 'blob-connector-filesystem:generate-shareLink-error');
        }

        $fileData->setContentUrl($shareLinkPersistence->getLink());

        //save data to database
        $this->shareLinkPersistenceService->saveShareLinkPersistence($shareLinkPersistence);

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
        try {
            $shareLinkPersistence = $this->generateShareLink($fileData, $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename']);
        } catch (FileException $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Sharelink could not generated', 'blob-connector-filesystem:generate-shareLink-error');
        }
        $fileData->setContentUrl($shareLinkPersistence->getLink());

        // Save shareLink to database
        $this->shareLinkPersistenceService->saveShareLinkPersistence($shareLinkPersistence);

        return $fileData;
    }

    public function removeFile(FileData $fileData): bool
    {
        // Delete ShareLinks
        $this->shareLinkPersistenceService->removeShareLinkPersistencesByFileDataID($fileData->getIdentifier());

        // Delete the file
        $destinationFilenameArray = $this->generatePath($fileData);
        $path = $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename'];

        FileOperations::removeFile($path, $destinationFilenameArray['destination']);

        return true;
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

    /**
     * @throws \Exception
     */
    private function generateShareLink(FileData $fileData, string $path): ShareLinkPersistence
    {
        $shareLinkId = (string) Uuid::v4();
        $shareLink = new ShareLinkPersistence();
        $shareLink->setIdentifier($shareLinkId);
        $shareLink->setFileDataIdentifier($fileData->getIdentifier());
        $contentUrl = $this->generateContentUrl($shareLinkId);
        $shareLink->setLink($contentUrl);

        // Create a valid until date
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($fileData->getBucket() && $fileData->getBucket()->getLinkExpireTime() !== null) {
            $linkExpireTime = $fileData->getBucket()->getLinkExpireTime();
        } else {
            $linkExpireTime = $this->configurationService->getLinkExpireTime();
        }

        $validUntil = $now->add(new \DateInterval($linkExpireTime));

        $shareLink->setValidUntil($validUntil);

        $shareLink->setFilesystemPath($path);

        return $shareLink;
    }
}
