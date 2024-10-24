<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Helper;

class FileOperations
{
    public static function isDirEmpty(string $dir)
    {
        if (!is_readable($dir)) {
            return null;
        }

        return count(scandir($dir)) === 2;
    }
}
