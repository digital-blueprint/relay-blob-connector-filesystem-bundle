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

    public function testSaveGetRemoveFile()
    {
        $fileDataId = (string) Uuid::v4();
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setFile($this->getExampleFile());

        $this->fileSystemService->saveFile($fileData);

        $ret = $this->fileSystemService->removeFile($fileData);
        $this->assertTrue($ret);

        // check dir empty
        $this->assertNull(FileOperations::isDirEmpty('testfile'));
    }
}
