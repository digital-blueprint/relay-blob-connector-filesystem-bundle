<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Tests\Service;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ConfigurationService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\FilesystemService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

class FilesystemServiceTest extends WebTestCase
{
    private ConfigurationService $configurationService;

    private FilesystemService $fileSystemService;

    private Filesystem $filesystem;
    private string $tempDir;
    private string $bucketDir;
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/'.uniqid('test_', true);
        $this->bucketDir = $this->tempDir . '/bucket';
        $this->uploadDir = $this->tempDir . '/upload';
        $this->filesystem->mkdir($this->bucketDir);
        $this->filesystem->mkdir($this->uploadDir);

        $config = ['path' => $this->bucketDir];
        $this->configurationService = new ConfigurationService();
        $this->configurationService->setConfig($config);
        $this->fileSystemService = new FilesystemService($this->configurationService);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    private function getExampleFile(): File
    {
        $filesystem = new Filesystem();
        $tempFile = $filesystem->tempnam($this->uploadDir, 'blob_fs_');
        $file = dirname(__FILE__).DIRECTORY_SEPARATOR.'test.pdf';
        $this->assertTrue(copy($file, $tempFile), 'Copy testfile went wrong');
        $uploadedFile = new File($tempFile, true);
        return $uploadedFile;
    }

    private function getAllPaths(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->bucketDir, \RecursiveDirectoryIterator::SKIP_DOTS|\FilesystemIterator::CURRENT_AS_SELF),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $paths = [];
        foreach ($iterator as $item) {
            $paths[] = $item->getSubPathname();
        }
        sort($paths);
        return $paths;
    }

    public function testSaveGetRemoveFile()
    {
        $fileDataId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setFile($this->getExampleFile());
        $this->assertSame([], $this->getAllPaths());

        $this->fileSystemService->saveFile($fileData);
        $this->assertSame([
            'a9',
            'a9/11',
            'a9/11/0192b970-cd6d-726d-a258-a911c5aac1b7',
        ], $this->getAllPaths());

        $ret = $this->fileSystemService->removeFile($fileData);
        $this->assertTrue($ret);
        $this->assertSame([
            'a9',
        ], $this->getAllPaths());
    }
}
