<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Entity\Bucket;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\PoliciesStruct;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Dbp\Relay\BlobConnectorFilesystemBundle\Entity\ShareLinkPersistence;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

class FilesystemService implements DatasystemProviderServiceInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var SluggerInterface
     */
    private $slugger;

    private $targetDirectory;

    private $sharedFileService;

    public function __construct(EntityManagerInterface $em, ConfigurationService $configurationService, SluggerInterface $slugger, SharedFileService $sharedFileService)
    {
        $this->configurationService = $configurationService;
        $this->em = $em;
        $this->slugger = $slugger;
        $this->sharedFileService = $sharedFileService;
    }

    public function checkConnection()
    {
        $this->em->getConnection()->connect();
    }

    public function saveFile(FileData &$fileData): ?FileData
    {
        try {
            $destinationFilenameArray = $this->generatePath($fileData);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Path could not be generated', 'blob-connector-filesystem:path-not-generated', ['message' => $e->getMessage()]);
        }

        /** @var ?UploadedFile $uploadedFile */
        $uploadedFile = $fileData->getFile();
        try {
            $uploadedFile->move($destinationFilenameArray['destination'], $destinationFilenameArray['filename']);
        } catch (FileException $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'File could not be uploaded', 'blob-connector-filesystem:save-file-error');
        }

        //generate link
        try {
            $shareLink = $this->generateShareLink($fileData->getIdentifier(), $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename']);
        } catch (FileException $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Sharelink could not generated', 'blob-connector-filesystem:generate-sharelink-error');
        }

        $fileData->setContentUrl($shareLink->getLink());

        //save data to database
        try {
            $this->em->persist($shareLink);
            $this->em->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'ShareLink could not be saved!', 'blob-connector-filesystem:sharelink-not-saved', ['message' => $e->getMessage()]);
        }

        return $fileData;
    }

    public function renameFile(FileData &$fileData): ?FileData
    {
        // Nothing todo here beause its only saved in blobBundle
        return $fileData;
    }

    public function getLink(FileData &$fileData, PoliciesStruct $policiesStruct): ?FileData
    {
        try {
            $destinationFilenameArray = $this->generatePath($fileData);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Path could not be generated', 'blob-connector-filesystem:path-not-generated', ['message' => $e->getMessage()]);
        }
        try {
            $shareLink = $this->generateShareLink($fileData->getIdentifier(), $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename']);
        } catch (FileException $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Sharelink could not generated', 'blob-connector-filesystem:generate-sharelink-error');
        }
        $fileData->setContentUrl($shareLink->getLink());

        //save sharelink to database
        try {
            $this->em->persist($shareLink);
            $this->em->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'ShareLink could not be saved!', 'blob-connector-filesystem:sharelink-not-saved', ['message' => $e->getMessage()]);
        }

        return $fileData;
    }

    public function removeFile(FileData &$fileData): bool
    {
        // Delete ShareLinks
        $this->sharedFileService->removeShareLinkPersistencesByFileDataID($fileData->getIdentifier());

        $destinationFilenameArray = $this->generatePath($fileData);
        $path = $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename'];

        // Remove File from server
        if (file_exists($path)) {
            unlink($path);
        }

        // Remove folder if empty
        if ($this->is_dir_empty($destinationFilenameArray['destination'])) {
            rmdir($destinationFilenameArray['destination']);
        }

        return true;
    }

    private function is_dir_empty($dir)
    {
        if (!is_readable($dir)) {
            return null;
        }

        return count(scandir($dir)) === 2;
    }

    public function removePathFromBucket(string $path, Bucket $bucket): bool
    {
        throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Not implemented', 'blob-connector-filesystem:not-implemented');

        return true;
    }

    public function removeBucket(Bucket $bucket): bool
    {
        throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Not implemented', 'blob-connector-filesystem:not-implemented');

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

    private function generateShareLink(string $fileDataIde, string $path): ShareLinkPersistence
    {
        $shareLinkId = (string) Uuid::v4();
        $shareLink = new ShareLinkPersistence();
        $shareLink->setIdentifier($shareLinkId);
        $shareLink->setFileDataIdentifier($fileDataIde);
        $contentUrl = $this->generateContentUrl($shareLinkId);
        $shareLink->setLink($contentUrl);

        // Create a valid until date
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $linkExpireTime = $this->configurationService->getLinkExpireTime();

        $validUntil = $now->add(new \DateInterval($linkExpireTime));

        $shareLink->setValidUntil($validUntil);

        $shareLink->setFilesystemPath($path);

        return $shareLink;
    }
}
