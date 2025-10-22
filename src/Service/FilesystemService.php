<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobBundle\Service\DatasystemProviderServiceInterface;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Filesystem\Filesystem;

class FilesystemService implements DatasystemProviderServiceInterface
{
    /** @var resource|null */
    private mixed $backupFile;

    private string $backupFileName;

    public function __construct(
        private ConfigurationService $configurationService)
    {
        $this->backupFile = null;
        $this->backupFileName = 'metadata-backup.jsonl';
    }

    /**
     * Check if the configured target path is usable and create it if not.
     */
    public function ensurePath(): string
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

        return $path;
    }

    public function saveFile(string $internalBucketId, string $fileId, \SplFileInfo $file): void
    {
        $this->ensurePath();
        $destinationFilenameArray = $this->generatePath($internalBucketId, $fileId);

        // Create all directories except the root
        $root = $destinationFilenameArray['root'];
        $dirs = $destinationFilenameArray['dirs'];
        $currentDir = $root;
        foreach ($dirs as $dir) {
            $currentDir .= DIRECTORY_SEPARATOR.$dir;
            if (!file_exists($currentDir)) {
                // In case something else created the dir in the meantime, mkdir will fail, so check again afterward
                if (mkdir($currentDir) !== true && !file_exists($currentDir)) {
                    throw new \RuntimeException("Unable to create directory $currentDir");
                }
            }
        }

        // Move the file into place
        $basename = $destinationFilenameArray['basename'];
        $tmpFilePath = tempnam($currentDir, 'tmp_'.$basename.'_');
        if ($tmpFilePath === false) {
            throw new \RuntimeException("Unable to create temp file in $currentDir");
        }
        // tempnam() can fall back to the system temp dir, which we don't want
        if (realpath(dirname($tmpFilePath)) !== realpath($currentDir)) {
            @unlink($tmpFilePath);
            throw new \RuntimeException("Unable to create temp file in $currentDir");
        }

        // Move the file to a temp file in the same dir
        $srcFilePath = $file->getRealPath();
        if ($srcFilePath === false) {
            throw new \RuntimeException('File to save does not exist: '.$file->getPathname());
        }
        if (rename($srcFilePath, $tmpFilePath) === false) {
            throw new \RuntimeException(sprintf('Could not move the file "%s" to "%s".', $srcFilePath, $tmpFilePath));
        }

        try {
            @chmod($tmpFilePath, 0o666 & ~umask());

            $tmpFileResource = fopen($tmpFilePath, 'r');
            if ($tmpFileResource === false) {
                throw new \RuntimeException(sprintf('Could not open the file "%s" for reading.', $tmpFilePath));
            }
            try {
                if (fsync($tmpFileResource) === false) {
                    throw new \RuntimeException(sprintf('Could not synchronise the file "%s".', $tmpFilePath));
                }
            } finally {
                fclose($tmpFileResource);
            }

            // Finally, move to the target
            $target = $destinationFilenameArray['path'];
            if (false === rename($tmpFilePath, $target)) {
                throw new \RuntimeException(sprintf('Could not move the file "%s" to "%s".', $tmpFilePath, $target));
            }
            // fsync() in PHP doesn't support directories, so if we crash after this, the client might never know
        } finally {
            @unlink($tmpFilePath);
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

    public function getFileStream(string $internalBucketId, string $fileId): StreamInterface
    {
        $filePath = $this->getFilePath($internalBucketId, $fileId);
        $fileResource = @fopen($filePath, 'r');
        if ($fileResource === false) {
            throw new \RuntimeException("Could not open the file $filePath for reading");
        }

        return Utils::streamFor($fileResource);
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

    public function getFilePath(string $internalBucketId, string $fileId): string
    {
        return $this->generatePath($internalBucketId, $fileId)['path'];
    }

    public function hasFile(string $internalBucketId, string $fileId): bool
    {
        return file_exists($this->getFilePath($internalBucketId, $fileId));
    }

    public function listFiles(string $internalBucketId): iterable
    {
        return $this->listFilePaths($internalBucketId, true);
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

    private function generatePath(string $internalBucketId, ?string $fileId = null): array
    {
        $numOfChars = 2;
        $baseOffset = 24;

        // While we assume UUIDs v7 here, make sure there are no path traversal things possible
        if (str_contains($fileId, DIRECTORY_SEPARATOR) || str_contains($fileId, '.')) {
            throw new \RuntimeException('Invalid file ID');
        }

        $folder = substr($fileId, $baseOffset, $numOfChars);
        $nextFolder = substr($fileId, $baseOffset + $numOfChars, $numOfChars);

        // So we never generate a different structure
        if ($folder === '' || $nextFolder === '') {
            throw new \RuntimeException('Invalid file ID');
        }

        $rootPath = $this->getRootPath();
        $path = $this->getBucketPath($internalBucketId, $rootPath).DIRECTORY_SEPARATOR.$folder.
            DIRECTORY_SEPARATOR.$nextFolder.DIRECTORY_SEPARATOR.$fileId;

        return [
            'root' => $rootPath,
            'dirs' => [$internalBucketId, $folder, $nextFolder],
            'basename' => $fileId,
            'path' => $path,
        ];
    }

    private function getRootPath(): string
    {
        return rtrim($this->configurationService->getPath(), DIRECTORY_SEPARATOR);
    }

    private function getBucketPath(string $internalBucketId, ?string $rootPath = null): string
    {
        if (str_contains($internalBucketId, DIRECTORY_SEPARATOR)
            || str_contains($internalBucketId, '.') || $internalBucketId === '') {
            throw new \RuntimeException('Invalid internal bucket ID');
        }
        $rootPath ??= $this->getRootPath();

        return $rootPath.DIRECTORY_SEPARATOR.$internalBucketId;
    }

    private function listFilePaths(string $internalBucketId, bool $baseNameOnly = false): iterable
    {
        $bucketPath = $this->getBucketPath($internalBucketId);
        if (!is_dir($bucketPath)) {
            return [];
        }

        $recursiveDirectoryIterator = new \RecursiveDirectoryIterator($bucketPath,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_SELF);

        /** @var \RecursiveDirectoryIterator $dirIterator */
        foreach (new \RecursiveIteratorIterator($recursiveDirectoryIterator) as $dirIterator) {
            if ($dirIterator->isFile()) {
                yield $baseNameOnly ? $dirIterator->getFilename() : $dirIterator->getPathname();
            }
        }
    }

    public function openMetadataBackup(string $interalBucketId, string $mode): bool
    {
        if ($mode !== 'r' && $mode !== 'w') {
            throw new \RuntimeException("mode $mode is not supported, only r and w are supported");
        }
        $path = rtrim($this->configurationService->getPath(), '/');
        if (!is_dir($path) || !is_dir($path.'/'.$interalBucketId.'/')) {
            return false;
        }

        $ret = fopen($path.'/'.$interalBucketId.'/'.$this->backupFileName, $mode);

        if ($ret !== false) {
            $this->backupFile = $ret;
        }

        return $ret !== false;
    }

    public function appendToMetadataBackup(string $item): bool
    {
        $ret = fwrite($this->backupFile, $item);

        return $ret !== false;
    }

    public function retrieveItemFromMetadataBackup(): string|false
    {
        $ret = fgets($this->backupFile);

        if (!$ret && !feof($this->backupFile)) {
            throw new \RuntimeException("Could not read line from metadata backup!");
        }

        return $ret;
    }
    public function hasNextItemInMetadataBackup(): bool
    {
        return feof($this->backupFile);
    }

    public function closeMetadataBackup(string $interalBucketId): bool
    {
        $ret = fclose($this->backupFile);

        return $ret !== false;
    }

    public function getMetadataBackupFileHash(string $intBucketId): ?string
    {
        $ret = hash_file('sha256', rtrim($this->configurationService->getPath(), '/').'/'.$intBucketId.'/'.$this->backupFileName);
        if ($ret === false) {
            return null;
        }

        return $ret;
    }

    public function getMetadataBackupFileRef(string $intBucketId): ?string
    {
        return rtrim($this->configurationService->getPath(), '/').'/'.$intBucketId.'/'.$this->backupFileName;
    }
}
