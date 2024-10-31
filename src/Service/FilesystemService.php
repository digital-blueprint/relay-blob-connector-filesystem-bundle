<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;

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
     * Check if the configured target path is usable.
     */
    public function checkPath(): void
    {
        $path = $this->configurationService->getPath();
        if (!file_exists($path)) {
            throw new \RuntimeException("$path does not exist");
        } elseif (!is_dir($path)) {
            throw new \RuntimeException("$path is not a directory");
        } elseif (!is_readable($path)) {
            throw new \RuntimeException("$path is not readable");
        } elseif (!is_writable($path)) {
            throw new \RuntimeException("$path is not writable");
        }
    }

    public function saveFile(FileData $fileData): void
    {
        $this->checkPath();
        $destinationFilenameArray = $this->generatePath($fileData);

        // Create all directories except the root
        $root = $destinationFilenameArray['root'];
        $dirs = $destinationFilenameArray['dirs'];
        $currentDir = $root;
        foreach ($dirs as $dir) {
            $currentDir .= '/'.$dir;
            if (!file_exists($currentDir)) {
                // In case something else created the dir in the meantime, mkdir will fail, so check again afterward
                if (mkdir($currentDir) !== true && !file_exists($currentDir)) {
                    throw new \RuntimeException("Unable to create $currentDir");
                }
            }
        }

        // Move the file into place
        $src = $fileData->getFile()->getPathname();
        $target = $destinationFilenameArray['path'];
        $renamed = rename($src, $target);
        if (!$renamed) {
            throw new \RuntimeException(sprintf('Could not move the file "%s" to "%s".', $src, $target));
        }
        @chmod($target, 0o666 & ~umask());
    }

    public function getContentUrl(FileData $fileData): string
    {
        $filePath = $this->getFilePath($fileData);

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

        return 'data:'.$mimeType.';base64,'.base64_encode($file);
    }

    public function getSumOfFilesizesOfBucket(string $bucketId): int
    {
        // size of all files in the filesystem
        $sumOfFileSizes = 0;

        // check if directory exists
        if (!is_dir($this->configurationService->getPath().'/'.$bucketId)) {
            return 0;
        }

        /* iterate over first level of subdirectories in bucket dir, if no failure */
        $subdirs = scandir($this->configurationService->getPath().'/'.$bucketId);
        if (!$subdirs) {
            return -1;
        }
        foreach ($subdirs as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }

            /* iterate over second level of subdirectories in bucket dir, if no failure */
            $subsubdirs = scandir($this->configurationService->getPath().'/'.$bucketId.'/'.$subdir);
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
                $files = scandir($this->configurationService->getPath().'/'.$bucketId.'/'.$subdir.'/'.$subsubdir);
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

                    $sumOfFileSizes += filesize($this->configurationService->getPath().'/'.$bucketId.'/'.$subdir.'/'.$subsubdir.'/'.$file);
                }
            }
        }

        return $sumOfFileSizes;
    }

    public function getNumberOfFilesInBucket(string $bucketId): int
    {
        // size of all files in the filesystem
        $numOfFiles = 0;

        // check if directory exists
        if (!is_dir($this->configurationService->getPath().'/'.$bucketId)) {
            return 0;
        }

        /* iterate over first level of subdirectories in bucket dir, if no failure */
        $subdirs = scandir($this->configurationService->getPath().'/'.$bucketId);
        if (!$subdirs) {
            return -1;
        }
        foreach ($subdirs as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }

            /* iterate over second level of subdirectories in bucket dir, if no failure */
            $subsubdirs = scandir($this->configurationService->getPath().'/'.$bucketId.'/'.$subdir);
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
                $files = scandir($this->configurationService->getPath().'/'.$bucketId.'/'.$subdir.'/'.$subsubdir);
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

    public function getBinaryResponse(FileData $fileData): Response
    {
        $filePath = $this->getFilePath($fileData);

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

        return $response;
    }

    public function removeFile(FileData $fileData): void
    {
        // Delete the file
        $path = $this->getFilePath($fileData);

        if (!file_exists($path)) {
            throw new \RuntimeException('File does not exist: '.$path);
        } else {
            unlink($path);
        }
    }

    private function generatePath(FileData $fileData): array
    {
        $numOfChars = 2;
        $baseOffset = 24;

        $bucketId = $fileData->getInternalBucketID();
        $id = $fileData->getIdentifier();
        $folder = substr($id, $baseOffset, $numOfChars);
        $nextFolder = substr($id, $baseOffset + $numOfChars, $numOfChars);
        $destination = rtrim($this->configurationService->getPath(), '/');

        $path = $destination.'/'.$bucketId.'/'.$folder.'/'.$nextFolder.'/'.$id;

        return ['root' => $destination, 'dirs' => [$bucketId, $folder, $nextFolder], 'basename' => $id, 'path' => $path];
    }

    public function getFilePath(FileData $fileData): string
    {
        return $this->generatePath($fileData)['path'];
    }
}
