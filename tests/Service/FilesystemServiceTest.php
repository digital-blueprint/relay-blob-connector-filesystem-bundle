<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Tests\Service;

use Dbp\Relay\BlobBundle\Entity\Bucket;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ConfigurationService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\FilesystemService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

class FilesystemServiceTest extends WebTestCase
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var FilesystemService
     */
    private $fileSystemService;

    private Filesystem $filesystem;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/'.uniqid('test_', true);
        $this->filesystem->mkdir($this->tempDir);

        $config = ['path' => $this->tempDir, 'link_url' => 'http://localhost:8000/', 'link_expire_time' => 'P7D'];
        $this->configurationService = new ConfigurationService();
        $this->configurationService->setConfig($config);

        $slugger = new AsciiSlugger();

        $this->fileSystemService = new FilesystemService($this->configurationService, $slugger);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    private function getExampleFile(): UploadedFile
    {
        $examplePath = dirname(__FILE__).DIRECTORY_SEPARATOR.'test.pdf';
        // copy file for testing
        $file = dirname(__FILE__).DIRECTORY_SEPARATOR.'test_original.pdf';

        if (!copy($file, $examplePath)) {
            assert('Copy testfile went wrong');
        }

        $uploadedFile = new UploadedFile($examplePath, 'test', 'pdf', null, true);

        return $uploadedFile;
    }

    public function testSaveGetRemoveFile()
    {
        $bucket = new Bucket();
        $bucket->setLinkExpireTime('P1D');
        $bucket->setKey('v3fbdbyf2f0muqvl0t2mdixlteaxs45fsicrczavbec95fsr9rtx3x89fum1euir');
        $bucket->setIdentifier((string) Uuid::v4());
        $fileDataId = (string) Uuid::v4();
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setFile($this->getExampleFile());
        $fileData->setBucket($bucket);

        $fileData->setDeleteAt(null);

        $fileDataSaved = $this->fileSystemService->saveFile($fileData);

        $this->fileSystemService->getLink($fileDataSaved);

        $ret = $this->fileSystemService->removeFile($fileData);
        $this->assertTrue($ret);

        // check dir empty
        $this->assertNull(FileOperations::isDirEmpty('testfile'));
    }
}
