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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Symfony\Component\Mime\MimeTypes;
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
        // set the expiry time
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
     * Get HTTP link to binary content.
     *
     * @param FileData       $fileData       fileData for which a link should be provided
     * @param PoliciesStruct $policiesStruct policies
     *
     * @throws \Exception
     */
    public function getLink(FileData $fileData, PoliciesStruct $policiesStruct): ?FileData
    {
        // the file link should expire in the near future
        // set the expiry time
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

    public function getFilePath(FileData $fileData): string
    {
        $filePath = $this->getPath($fileData);

        // if file doesnt exist, then the same file with extension should exist
        // file extensions were removed in version v0.1.7
        if (!file_exists($filePath)) {
            $mimeTypeToExt = new MimeTypes();
            $types = $mimeTypeToExt->getExtensions($fileData->getMimeType());
            foreach ($types as $ext) {
                $newPath = $filePath.'.'.$ext;
                if (file_exists($newPath)) {
                    $filePath = $newPath;
                }
            }
        }

        return $filePath;
    }

    /**
     * @throws \Exception
     */
    public function getBase64Data(FileData $fileData, PoliciesStruct $policiesStruct): FileData
    {
        /** @var string $filePath */
        $filePath = $this->getFilePath($fileData);

        try {
            // build binary response
            $file = file_get_contents($filePath);
            $mimeTypeGuesser = new FileinfoMimeTypeGuesser();

            // Set the mimetype with the guesser or manually
            if ($mimeTypeGuesser->isGuesserSupported()) {
                // Guess the mimetype of the file according to the extension of the file
                $mimeType = $mimeTypeGuesser->guessMimeType($filePath);
            } elseif ($fileData->getMimeType()) {
                // Set the mimetype of the file manually to the already set mimetype if guessing is impossible
                $mimeType = $fileData->getMimeType();
            } else {
                // Set the mimetype of the file manually, in this case for a text file is text/plain
                $mimeType = 'text/plain';
            }

            $filename = $fileData->getFileName();

            $fileData->setContentUrl('data:'.$mimeType.';base64,'.base64_encode($file));
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'File was not found', 'blob-connector-filesystem:file-not-found', ['message' => $e->getMessage()]);
        }

        return $fileData;
    }

    public function getBinaryResponse(FileData $fileData, PoliciesStruct $policiesStruct): Response
    {
        /** @var string $filePath */
        $filePath = $this->getFilePath($fileData);

        try {
            $response = new BinaryFileResponse($filePath);
            $mimeTypeGuesser = new FileinfoMimeTypeGuesser();

            // Set the mimetype with the guesser or manually
            if ($mimeTypeGuesser->isGuesserSupported()) {
                // Guess the mimetype of the file according to the extension of the file
                $response->headers->set('Content-Type', $mimeTypeGuesser->guessMimeType($filePath));
            } elseif ($fileData->getMimeType()) {
                // Set the mimetype of the file manually to the already set mimetype if guessing is impossible
                $response->headers->set('Content-Type', $fileData->getMimeType());
            } else {
                // Set the mimetype of the file manually, in this case for a text file is text/plain
                $response->headers->set('Content-Type', 'text/plain');
            }

            $filename = $fileData->getFileName();

            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            );
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'File was not found', 'blob-connector-filesystem:file-not-found', ['message' => $e->getMessage()]);
        }

        return $response;
    }

    private function getPath($fileData): string
    {
        return $this->configurationService->getPath().'/'.substr($fileData->getIdentifier(), 0, 2).'/'.$fileData->getIdentifier();
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
        return hash('sha256', $contentUrl);
    }

    private function generatePath(FileData $fileData): array
    {
        $id = $fileData->getIdentifier();
        $folder = substr($id, 0, 2);
        $safeFilename = $this->slugger->slug($id);
        $newFilename = $safeFilename.'';
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
