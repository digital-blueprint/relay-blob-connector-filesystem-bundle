<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use DateTimeZone;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\DenyAccessUnlessCheckSignature;
use Dbp\Relay\BlobBundle\Helper\PoliciesStruct;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Symfony\Component\String\Slugger\SluggerInterface;

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

        // the file link should expire in the near future
        // set the expire time
        $now = new \DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now->add(new \DateInterval($fileData->getBucket()->getLinkExpireTime()));

        $payload = [
            'identifier' => $fileData->getIdentifier(),
            'validUntil' => $now,
        ];

        // set content url
        $contentUrl = $this->generateSignedContentUrl($fileData->getIdentifier(), $now->format('c'), DenyAccessUnlessCheckSignature::create($fileData->getBucket()->getKey(), $payload));
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
        // the file link should expire in the near future
        // set the expire time
        $now = new \DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $now->add(new \DateInterval($fileData->getBucket()->getLinkExpireTime()));

        $payload = [
            'cs' => $this->generateChecksumFromFileData($fileData, $now->format('c')),
        ];

        // set content url
        $contentUrl = $this->generateSignedContentUrl($fileData->getIdentifier(), $now->format('c'), DenyAccessUnlessCheckSignature::create($fileData->getBucket()->getKey(), $payload));
        $fileData->setContentUrl($this->configurationService->getLinkUrl().substr($contentUrl, 1));

        return $fileData;
    }

    /**
     * @throws \Exception
     */
    public function getBinaryData(FileData $fileData, PoliciesStruct $policiesStruct): FileData
    {
        // Check if sharelink is already invalid
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        /** @var string */
        $filePath = $this->getPath($fileData);

        // build binary response
        $file = file_get_contents($filePath);
        $mimeTypeGuesser = new FileinfoMimeTypeGuesser();

        // Set the mimetype with the guesser or manually
        if ($mimeTypeGuesser->isGuesserSupported()) {
            // Guess the mimetype of the file according to the extension of the file
            $mimeType = $mimeTypeGuesser->guessMimeType($filePath);
        } else {
            // Set the mimetype of the file manually, in this case for a text file is text/plain
            $mimeType = 'text/plain';
        }

        $filename = $fileData->getFileName();

        $fileData->setContentUrl('data:'.$mimeType.';base64,'.base64_encode($file));

        return $fileData;
    }

    private function getPath($fileData): string
    {
        return $this->configurationService->getPath().'/'.substr($fileData->getIdentifier(), 0, 2).'/'.$fileData->getIdentifier().'.'.$fileData->getExtension();
    }

    public function removeFile(FileData $fileData): bool
    {
        // Delete the file
        $destinationFilenameArray = $this->generatePath($fileData);
        $path = $destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename'];

        FileOperations::removeFile($path, $destinationFilenameArray['destination']);

        return true;
    }

    public function generateChecksumFromFileData($fileData, $validUntil = ''): ?string
    {
        // if no validUntil is given, use bucket link expiry time per default
        if ($validUntil === '') {
            $now = new \DateTimeImmutable('now', new DateTimeZone('UTC'));
            $now = $now->add(new \DateInterval($fileData->getBucket()->getLinkExpireTime()));
            $validUntil = $now->format('c');
        }

        // create url to hash
        $contentUrl = '/blob/filesystem/'.$fileData->getIdentifier().'?validUntil='.$validUntil;

        // create sha256 hash
        $cs = hash('sha256', $contentUrl);

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

        $destination .= $folder;

        return ['destination' => $destination, 'filename' => $newFilename];
    }

    private function generateContentUrl(string $id): string
    {
        $link = $this->configurationService->getLinkUrl();

        return '/blob/filesystem/'.$id;
    }

    private function generateContentUrlWithExpiry(string $id, string $validUntil): string
    {
        return $this->generateContentUrl($id).'?validUntil='.$validUntil;
    }

    private function generateSignedContentUrl(string $id, string $validUntil, string $signature): string
    {
        return $this->generateContentUrlWithExpiry($id, $validUntil).'&sig='.$signature;
    }
}
