<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Entity\Bucket;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\DenyAccessUnlessCheckSignature;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Symfony\Component\Mime\MimeTypes;

class FilesystemService implements DatasystemProviderServiceInterface
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    public function __construct(ConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;
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
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $now->add(new \DateInterval($fileData->getBucket()->getLinkExpireTime()));

        $payload = [
            'identifier' => $fileData->getIdentifier(),
            'validUntil' => $now,
        ];

        // set content url
        $contentUrl = $this->generateSignedContentUrl($fileData->getIdentifier(), rawurlencode($now->format('c')), DenyAccessUnlessCheckSignature::create($fileData->getBucket()->getKey(), $payload));
        $fileData->setContentUrl($contentUrl);

        // move file to correct destination
        FileOperations::moveFile($fileData->getFile(), $destinationFilenameArray['destination'], $destinationFilenameArray['filename']);

        return $fileData;
    }

    /**
     * @throws \Exception
     */
    public function saveFileFromString(FileData $fileData, string $data): ?FileData
    {
        try {
            $destinationFilenameArray = $this->generatePath($fileData);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Path could not be generated', 'blob-connector-filesystem:path-not-generated', ['message' => $e->getMessage()]);
        }

        // the file link should expire in the near future
        // set the expiry time
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $now->add(new \DateInterval($fileData->getBucket()->getLinkExpireTime()));

        $payload = [
            'identifier' => $fileData->getIdentifier(),
            'validUntil' => $now,
        ];

        // set content url
        $contentUrl = $this->generateSignedContentUrl($fileData->getIdentifier(), rawurlencode($now->format('c')), DenyAccessUnlessCheckSignature::create($fileData->getBucket()->getKey(), $payload));
        $fileData->setContentUrl($contentUrl);

        // move file to correct destination
        FileOperations::saveFileFromString($data, $destinationFilenameArray['destination'], $destinationFilenameArray['filename']);

        try {
            $fileData->setFile(new File($destinationFilenameArray['destination'].'/'.$destinationFilenameArray['filename']));
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'File could not be found', 'blob-connector-filesystem:file-not-found', ['message' => $e->getMessage()]);
        }

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
     * @param FileData $fileData fileData for which a link should be provided
     *
     * @throws \Exception
     */
    public function getLink(FileData $fileData): ?FileData
    {
        // the file link should expire in the near future
        // set the expiry time
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $now->add(new \DateInterval($fileData->getBucket()->getLinkExpireTime()));

        $payload = [
            'ucs' => $this->generateChecksumFromFileData($fileData, $now->format('c')),
        ];

        // set content url
        $contentUrl = $this->generateSignedContentUrl($fileData->getIdentifier(), rawurlencode($now->format('c')), DenyAccessUnlessCheckSignature::create($fileData->getBucket()->getKey(), $payload));
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
    public function getBase64Data(FileData $fileData): FileData
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

            $fileData->setContentUrl('data:'.$mimeType.';base64,'.base64_encode($file));
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'File was not found', 'blob-connector-filesystem:file-not-found', ['message' => $e->getMessage()]);
        }

        return $fileData;
    }

    public function getSumOfFilesizesOfBucket(Bucket $bucket): int
    {
        // size of all files in the filesystem
        $sumOfFileSizes = 0;

        // check if directory exists
        if (!is_dir($this->configurationService->getPath().'/'.$bucket->getIdentifier())) {
            return 0;
        }

        /* iterate over first level of subdirectories in bucket dir, if no failure */
        $subdirs = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier());
        if (!$subdirs) {
            return -1;
        }
        foreach ($subdirs as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }

            /* iterate over second level of subdirectories in bucket dir, if no failure */
            $subsubdirs = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir);
            if (!$subsubdirs) {
                return -1;
            }
            // check if files other than . and .. are available
            if (count($subsubdirs) <= 2) {
                continue;
            }
            foreach ($subsubdirs as $subsubdir) {
                if ($subsubdir === '.' || $subsubdir === '..') {
                    continue;
                }

                /* iterate over all files if some are available and if no failure */
                $files = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir.'/'.$subsubdir);
                if (!$files) {
                    return -1;
                }
                // check if files other than . and .. are available
                if (count($files) <= 2) {
                    continue;
                }
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $sumOfFileSizes += filesize($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir.'/'.$subsubdir.'/'.$file);
                }
            }
        }

        return $sumOfFileSizes;
    }

    public function getNumberOfFilesInBucket(Bucket $bucket): int
    {
        // size of all files in the filesystem
        $numOfFiles = 0;

        // check if directory exists
        if (!is_dir($this->configurationService->getPath().'/'.$bucket->getIdentifier())) {
            return 0;
        }

        /* iterate over first level of subdirectories in bucket dir, if no failure */
        $subdirs = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier());
        if (!$subdirs) {
            return -1;
        }
        foreach ($subdirs as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }

            /* iterate over second level of subdirectories in bucket dir, if no failure */
            $subsubdirs = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir);
            if (!$subsubdirs) {
                return -1;
            }
            // check if files other than . and .. are available
            if (count($subsubdirs) <= 2) {
                continue;
            }
            foreach ($subsubdirs as $subsubdir) {
                if ($subsubdir === '.' || $subsubdir === '..') {
                    continue;
                }

                /* iterate over all files if some are available and if no failure */
                $files = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir.'/'.$subsubdir);
                if (!$files) {
                    return -1;
                }
                // check if files other than . and .. are available
                if (count($files) <= 2) {
                    continue;
                }
                $numOfFiles += (count($files) - 2);
            }
        }

        return $numOfFiles;
    }

    /**
     * Gets sum of filesizes and number of files that are saved in the filesystem for a given bucket
     * First value is the sum of filesizes, second value is the number of files.
     *
     * There are functions available that return this values separately, but if you need both,
     * this function provides better performance
     *
     * @return array|int[]
     */
    public function getSumOfFilesizesAndNumberOfFilesOfBucket(Bucket $bucket): array
    {
        // size of all files in the filesystem
        $sumOfFileSizes = 0;
        // number of all files in the filesystem
        $numOfFiles = 0;

        // check if directory exists
        if (!is_dir($this->configurationService->getPath().'/'.$bucket->getIdentifier())) {
            return [0, 0];
        }

        /* iterate over first level of subdirectories in bucket dir, if no failure */
        $subdirs = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier());

        if (!$subdirs) {
            return [-1, -1];
        }
        foreach ($subdirs as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }

            /* iterate over second level of subdirectories in bucket dir, if no failure */
            $subsubdirs = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir);
            if (!$subsubdirs) {
                return [-1, -1];
            }
            // check if files other than . and .. are available
            if (count($subsubdirs) <= 2) {
                continue;
            }
            foreach ($subsubdirs as $subsubdir) {
                if ($subsubdir === '.' || $subsubdir === '..') {
                    continue;
                }

                /* iterate over all files if some are available and if no failure */
                $files = scandir($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir.'/'.$subsubdir);
                if (!$files) {
                    return [-1, -1];
                }
                // check if files other than . and .. are available
                if (count($files) <= 2) {
                    continue;
                }
                $numOfFiles += (count($files) - 2);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $sumOfFileSizes += filesize($this->configurationService->getPath().'/'.$bucket->getIdentifier().'/'.$subdir.'/'.$subsubdir.'/'.$file);
                }
            }
        }

        return [$sumOfFileSizes, $numOfFiles];
    }

    public function getBinaryResponse(FileData $fileData): Response
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

    /**
     * @param $fileData FileData
     */
    private function getPath($fileData): string
    {
        $numOfChars = 2;
        $baseOffset = 24;

        return $this->configurationService->getPath().'/'.$fileData->getBucket()->getIdentifier().'/'.substr($fileData->getIdentifier(), $baseOffset, $numOfChars).'/'.substr($fileData->getIdentifier(), $baseOffset + $numOfChars, $numOfChars).'/'.$fileData->getIdentifier();
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
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $now = $now->add(new \DateInterval($fileData->getBucket()->getLinkExpireTime()));
            $validUntil = $now->format('c');
        }

        // create url to hash
        $contentUrl = '/blob/filesystem/'.$fileData->getIdentifier().'?validUntil='.rawurlencode($validUntil);

        // create sha256 hash
        return hash('sha256', $contentUrl);
    }

    private function generatePath(FileData $fileData): array
    {
        $numOfChars = 2;
        $baseOffset = 24;

        $bucketId = $fileData->getBucket()->getIdentifier();
        $id = $fileData->getIdentifier();
        $folder = substr($id, $baseOffset, $numOfChars);
        $nextFolder = substr($id, $baseOffset + $numOfChars, $numOfChars);
        $destination = $this->configurationService->getPath();
        if (substr($destination, -1) !== '/') {
            $destination .= '/';
        }

        $destination = $destination.$bucketId.'/'.$folder.'/'.$nextFolder;

        return ['destination' => $destination, 'filename' => $id];
    }

    private function generateContentUrl(string $id): string
    {
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
