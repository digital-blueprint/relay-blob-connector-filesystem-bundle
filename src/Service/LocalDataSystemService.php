<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorLocalBundle\Service;

use Dbp\Relay\BlobBundle\Entity\Bucket;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\PoliciesStruct;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Dbp\Relay\BlobConnectorLocalBundle\Entity\ShareLinkPersistence;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\TextUI\XmlConfiguration\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\Response;

class LocalDataSystemService implements DatasystemProviderServiceInterface
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

    public function __construct(EntityManagerInterface $em,  ConfigurationService $configurationService,  SluggerInterface $slugger)
    {
        $this->configurationService = $configurationService;
        $this->em = $em;
        $this->slugger = $slugger;
    }

    public function checkConnection()
    {
        $this->em->getConnection()->connect();
    }

    public function saveFile(FileData &$fileData): ?FileData
    {
        $shareLink = new ShareLinkPersistence();


        /** @var ?UploadedFile $uploadedFile */
        $uploadedFile = $fileData->getFile();
        $folder = substr($fileData->getIdentifier(), 0, 2);
        $id = $fileData->getIdentifier();
        $safeFilename = $this->slugger->slug($id);
        $newFilename = $safeFilename.'.'.$uploadedFile->guessExtension();
        $destination = $this->configurationService->getPath();
        if (substr($destination, -1) != '/') {
            $destination .= '/';
        }
        $destination .= $fileData->getBucket()->getPath();
        if (substr($destination, -1) != '/') {
            $destination .= '/';
        }
        $destination .= $folder;

        try {
            $uploadedFile->move($destination, $newFilename);
        } catch (FileException $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'File could not be uploaded', 'blob-connector-local:save-file-error');
        }


        /* Move File from images to copyImages folder

        if( !rename($uploadedFile, $destinationFilePath) ) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'File could not be uploaded', 'blob-connector-local:save-file-error');
        }*/

        //generate link
        $contentUrl = $this->generateContentUrl();
        $fileData->setContentUrl($contentUrl);

        $shareLink->setFileDataIdentifier($id);
        $shareLink->setLink($contentUrl);

        // Create a valid until date
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $linkExpireTime = $this->configurationService->getLinkExpireTime();
        $validUntil = $now->add(new \DateInterval('PT'.$linkExpireTime));
        $shareLink->setValidUntil($validUntil); //get valid out from config

        //save data to database
        try {
            $this->em->persist($shareLink);
            $this->em->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'ShareLink could not be saved!', 'blob-connector-local:sharelink-not-saved', ['message' => $e->getMessage()]);
        }
        return $fileData;
    }

    public function renameFile(FileData &$fileData): ?FileData
    {
        return null;
    }

    public function getLink(FileData &$fileData, PoliciesStruct $policiesStruct): ?FileData
    {
        return null;
    }

    public function removeFile(FileData &$fileData): bool
    {
        return true;
    }

    public function removePathFromBucket(string $path, Bucket $bucket): bool
    {
        return true;
    }

    public function removeBucket(Bucket $bucket): bool
    {
        return true;
    }

    private function generateContentUrl(): string
    {
        $link = $this->configurationService->getLinkUrl();
        $hash = bin2hex(random_bytes(22));
        //TODO check hash exists
        return $link.'blob/'.$hash;
    }
}
