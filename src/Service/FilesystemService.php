<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use Symfony\Component\Filesystem\Filesystem;
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
     * Check if the configured target path is usable, and create it if not.
     */
    public function checkPath(): void
    {
        $path = $this->configurationService->getPath();

        // Doesn't exist and isn't a broken symlink
        if (!file_exists($path) && !is_link($path)) {
            if ($this->configurationService->getCreatePath()) {
                $filesystem = new Filesystem();
                $filesystem->mkdir($path);
            }
        }

        if (!file_exists($path)) {
            if (is_link($path)) {
                throw new \RuntimeException("$path is a broken link");
            }
            throw new \RuntimeException("$path does not exist");
        } elseif (!is_dir($path)) {
            throw new \RuntimeException("$path is not a directory");
        } elseif (!is_readable($path)) {
            throw new \RuntimeException("$path is not readable");
        } elseif (!is_writable($path)) {
            throw new \RuntimeException("$path is not writable");
        }
    }

    public function saveFile(string $internalBucketId, string $fileId, File $file): void
    {
        $this->checkPath();
        $destinationFilenameArray = $this->generatePath($internalBucketId, $fileId);

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

    public function getSumOfFilesizesOfBucket(string $internalBucketId): int
    {
        $sumOfFileSizes = 0;
        foreach ($this->listFilePaths($internalBucketId) as $filePath) {
            $sumOfFileSizes += filesize($filePath);
        }

        return $sumOfFileSizes;
    }

    public function getNumberOfFilesInBucket(string $internalBucketId): int
    {
        $numOfFiles = 0;
        foreach ($this->listFilePaths($internalBucketId) as $filePath) {
            ++$numOfFiles;
        }

        return $numOfFiles;
    }

    public function getBinaryResponse(string $internalBucketId, string $fileId): Response
    {
        $filePath = $this->getFilePath($internalBucketId, $fileId);

        return new BinaryFileResponse($filePath);
    }

    public function removeFile(string $internalBucketId, string $fileId): void
    {
        // Delete the file
        $path = $this->getFilePath($internalBucketId, $fileId);

        if (!file_exists($path)) {
            throw new \RuntimeException('File does not exist: '.$path);
        } else {
            if (unlink($path) === false) {
                throw new \RuntimeException('Could not delete the file: '.$path);
            }
        }
    }

    private function generatePath(string $internalBucketId, string $fileId): array
    {
        $numOfChars = 2;
        $baseOffset = 24;

        $id = $fileId;
        // While we assume UUIDs v7 here, make sure there are no path traversal things possible
        if (str_contains($internalBucketId, '/') || str_contains($id, '/') || str_contains($internalBucketId, '.') || str_contains($id, '.')) {
            throw new \RuntimeException('Invalid ID');
        }

        $folder = substr($id, $baseOffset, $numOfChars);
        $nextFolder = substr($id, $baseOffset + $numOfChars, $numOfChars);
        $destination = rtrim($this->configurationService->getPath(), '/');

        // So we never generate a different structure
        if ($internalBucketId === '' || $folder === '' || $nextFolder === '') {
            throw new \RuntimeException('Invalid ID');
        }

        $path = $destination.'/'.$internalBucketId.'/'.$folder.'/'.$nextFolder.'/'.$id;

        return ['root' => $destination, 'dirs' => [$internalBucketId, $folder, $nextFolder], 'basename' => $id, 'path' => $path];
    }

    public function getFilePath(string $internalBucketId, string $fileId): string
    {
        return $this->generatePath($internalBucketId, $fileId)['path'];
    }

    public function hasFile(string $internalBucketId, string $fileId): bool
    {
        return file_exists($this->getFilePath($internalBucketId, $fileId));
    }

    private function listFilePaths(string $internalBucketId): iterable
    {
        $this->checkPath();
        $path = $this->configurationService->getPath();
        $bucketPath = $path.DIRECTORY_SEPARATOR.$internalBucketId;

        if (!is_dir($bucketPath)) {
            return [];
        }

        $subdirs = scandir($bucketPath);
        if ($subdirs === false) {
            throw new \RuntimeException();
        }

        foreach ($subdirs as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }

            $subdirPath = $bucketPath.DIRECTORY_SEPARATOR.$subdir;
            $subsubdirs = scandir($subdirPath);
            if ($subsubdirs === false) {
                throw new \RuntimeException();
            }

            foreach ($subsubdirs as $subsubdir) {
                if ($subsubdir === '.' || $subsubdir === '..') {
                    continue;
                }

                $subsubdirPath = $subdirPath.DIRECTORY_SEPARATOR.$subsubdir;
                $files = scandir($subsubdirPath);
                if ($files === false) {
                    throw new \RuntimeException();
                }

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    yield $subsubdirPath.DIRECTORY_SEPARATOR.$file;
                }
            }
        }
    }

    public function listFiles(string $internalBucketId): iterable
    {
        foreach ($this->listFilePaths($internalBucketId) as $filePath) {
            yield basename($filePath);
        }
    }

    public function getFileSize(string $internalBucketId, string $fileId): int
    {
        $res = filesize($this->getFilePath($internalBucketId, $fileId));
        if ($res === false) {
            throw new \RuntimeException();
        }

        return $res;
    }

    public function getFileHash(string $internalBucketId, string $fileId): string
    {
        $path = $this->getFilePath($internalBucketId, $fileId);
        $res = \hash_file('sha256', $path);
        if ($res === false) {
            throw new \RuntimeException();
        }

        return $res;
    }
}
