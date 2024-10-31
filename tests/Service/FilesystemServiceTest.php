<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Tests\Service;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ConfigurationService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\FilesystemService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;

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
        $this->bucketDir = $this->tempDir.'/bucket';
        $this->uploadDir = $this->tempDir.'/upload';
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
            new \RecursiveDirectoryIterator($this->bucketDir, \RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_SELF),
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
        $fileData->setInternalBucketID('154cc850-ede8-4c10-bff5-4e24f2ef6087');
        $this->assertSame([], $this->getAllPaths());

        $this->fileSystemService->saveFile($fileData);
        $this->assertSame([
            '154cc850-ede8-4c10-bff5-4e24f2ef6087',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9/11',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9/11/0192b970-cd6d-726d-a258-a911c5aac1b7',
        ], $this->getAllPaths());

        $this->fileSystemService->removeFile($fileData);
        $this->assertSame([
            '154cc850-ede8-4c10-bff5-4e24f2ef6087',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9/11',
        ], $this->getAllPaths());
    }

    public function testGetBase64Data()
    {
        $fileDataId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setInternalBucketID('154cc850-ede8-4c10-bff5-4e24f2ef6087');
        $fileData->setFile($this->getExampleFile());
        $this->fileSystemService->saveFile($fileData);

        $url = $this->fileSystemService->getContentUrl($fileData);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('data:application/pdf;', $url);
    }

    public function testGetBinaryResponse()
    {
        $fileDataId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setFile($this->getExampleFile());
        $fileData->setFileName('foobar.jpg');
        $fileData->setInternalBucketID('154cc850-ede8-4c10-bff5-4e24f2ef6087');
        $content = $fileData->getFile()->getContent();
        $this->fileSystemService->saveFile($fileData);

        $response = $this->fileSystemService->getBinaryResponse($fileData);
        assert($response instanceof BinaryFileResponse);
        $this->assertSame($content, $response->getFile()->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=foobar.jpg', $response->headers->get('Content-Disposition'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetBinaryResponseNoExist()
    {
        $fileDataId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setInternalBucketID('154cc850-ede8-4c10-bff5-4e24f2ef6087');
        $fileData->setFile($this->getExampleFile());
        $fileData->setMimeType('image/jpeg');

        $this->expectException(\Exception::class);
        $this->fileSystemService->getBinaryResponse($fileData);
    }

    public function testGetSumOfFilesizesAndNumberOfFilesOfBucket()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $sumSize = $this->fileSystemService->getSumOfFilesizesOfBucket($bucketId);
        $numFiles = $this->fileSystemService->getNumberOfFilesInBucket($bucketId);
        $this->assertSame(0, $sumSize);
        $this->assertSame(0, $numFiles);

        $fileDataId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setInternalBucketID('154cc850-ede8-4c10-bff5-4e24f2ef6087');
        $fileData->setFile($this->getExampleFile());
        $this->fileSystemService->saveFile($fileData);

        $sumSize = $this->fileSystemService->getSumOfFilesizesOfBucket($bucketId);
        $numFiles = $this->fileSystemService->getNumberOfFilesInBucket($bucketId);
        $this->assertSame(9243, $sumSize);
        $this->assertSame(1, $numFiles);

        $this->fileSystemService->removeFile($fileData);

        $sumSize = $this->fileSystemService->getSumOfFilesizesOfBucket($bucketId);
        $numFiles = $this->fileSystemService->getNumberOfFilesInBucket($bucketId);
        $this->assertSame(0, $sumSize);
        $this->assertSame(0, $numFiles);
    }

    public function testRemoveLostFile()
    {
        $fileDataId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setInternalBucketID('154cc850-ede8-4c10-bff5-4e24f2ef6087');
        $fileData->setFile($this->getExampleFile());
        $this->fileSystemService->saveFile($fileData);

        $path = $this->fileSystemService->getFilePath($fileData);
        unlink($path);

        $this->expectException(\Exception::class);
        $this->fileSystemService->removeFile($fileData);
    }

    public function testFailIfPathDoesntExist()
    {
        // In case the configured path doesn't exist we should fail and not silently create it
        $fileDataId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setInternalBucketID('154cc850-ede8-4c10-bff5-4e24f2ef6087');
        $fileData->setFile($this->getExampleFile());

        $this->filesystem->remove($this->bucketDir);

        $this->expectException(\Exception::class);
        $this->fileSystemService->saveFile($fileData);
    }
}
