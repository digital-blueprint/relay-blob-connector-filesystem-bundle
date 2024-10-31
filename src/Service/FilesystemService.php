<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;

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

    public function saveFile(string $bucketId, string $fileId, File $file): void
    {
        $this->checkPath();
        $destinationFilenameArray = $this->generatePath($bucketId, $fileId);

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
        $src = $file->getPathname();
        $basename = $destinationFilenameArray['basename'];
        $tmp = tempnam($currentDir, 'tmp_'.$basename.'_');
        if ($tmp === false) {
            throw new \RuntimeException("Unable to create temp file in $currentDir");
        }
        // tempnam() can fall back to the system temp dir, which we don't want
        if (realpath(dirname($tmp)) !== realpath($currentDir)) {
            @unlink($tmp);
            throw new \RuntimeException("Unable to create temp file in $currentDir");
        }
        $target = $destinationFilenameArray['path'];

        // Move the file to a temp file in the same dir
        $renamed = rename($src, $tmp);
        if (!$renamed) {
            throw new \RuntimeException(sprintf('Could not move the file "%s" to "%s".', $src, $target));
        }
        try {
            @chmod($tmp, 0o666 & ~umask());

            $handle = fopen($tmp, 'r');
            if ($handle === false) {
                throw new \RuntimeException(sprintf('Could not open the file "%s".', $tmp));
            }
            try {
                if (fsync($handle) === false) {
                    throw new \RuntimeException(sprintf('Could not synchronise the file "%s".', $tmp));
                }
            } finally {
                fclose($handle);
            }

            // Finally move to the target
            $renamed = rename($tmp, $target);
            if (!$renamed) {
                throw new \RuntimeException(sprintf('Could not move the file "%s" to "%s".', $tmp, $target));
            }

            // fsync() in PHP doesn't support directories, so if we crash after this the client might never know
        } finally {
            @unlink($tmp);
        }
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

    public function getBinaryResponse(string $bucketId, string $fileId): Response
    {
        $filePath = $this->getFilePath($bucketId, $fileId);

        return new BinaryFileResponse($filePath);
    }

    public function removeFile(string $bucketId, string $fileId): void
    {
        // Delete the file
        $path = $this->getFilePath($bucketId, $fileId);

        if (!file_exists($path)) {
            throw new \RuntimeException('File does not exist: '.$path);
        } else {
            if (unlink($path) === false) {
                throw new \RuntimeException('Could not delete the file: '.$path);
            }
        }
    }

    private function generatePath(string $bucketId, string $fileId): array
    {
        $numOfChars = 2;
        $baseOffset = 24;

        $id = $fileId;
        // While we assume UUIDs v7 here, make sure there are no path traversal things possible
        if (str_contains($bucketId, '/') || str_contains($id, '/') || str_contains($bucketId, '.') || str_contains($id, '.')) {
            throw new \RuntimeException('Invalid ID');
        }

        $folder = substr($id, $baseOffset, $numOfChars);
        $nextFolder = substr($id, $baseOffset + $numOfChars, $numOfChars);
        $destination = rtrim($this->configurationService->getPath(), '/');

        // So we never generate a different structure
        if ($bucketId === '' || $folder === '' || $nextFolder === '') {
            throw new \RuntimeException('Invalid ID');
        }

        $path = $destination.'/'.$bucketId.'/'.$folder.'/'.$nextFolder.'/'.$id;

        return ['root' => $destination, 'dirs' => [$bucketId, $folder, $nextFolder], 'basename' => $id, 'path' => $path];
    }

    public function getFilePath(string $bucketId, string $fileId): string
    {
        return $this->generatePath($bucketId, $fileId)['path'];
    }
}
