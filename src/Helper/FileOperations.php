<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Helper;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;

class FileOperations
{
    public static function isDirEmpty(string $dir)
    {
        if (!is_readable($dir)) {
            return null;
        }

        return count(scandir($dir)) === 2;
    }

    public static function moveFile(File $uploadedFile, string $dest, string $name)
    {
        try {
            $uploadedFile->move($dest, $name);
        } catch (FileException $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'File could not be uploaded', 'blob-connector-filesystem:save-file-error', ['message' => $e->getMessage()]);
        }
    }
}
