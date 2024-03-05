<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Tests\Service;

use Dbp\Relay\BlobBundle\Entity\Bucket;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Helper\PoliciesStruct;
use Dbp\Relay\BlobConnectorFilesystemBundle\Helper\FileOperations;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ConfigurationService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\FilesystemService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

    protected function setUp(): void
    {
        $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.'/../../src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $em = EntityManager::create(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ], $config
        );
        $em->getConnection()->executeQuery('CREATE TABLE blob_connector_filesystem (
              identifier varchar(50) NOT NULL,
              valid_until DATETIME NOT NULL,
              link varchar(255) NOT NULL,
              file_data_identifier varchar(255) NOT NULL,
              filesystem_path varchar(255) NOT NULL, PRIMARY KEY(identifier))');

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects($this->any())->method('getManager')->willReturn($em);

        $config = ['path' => dirname(__FILE__), 'link_url' => 'http://localhost:8000/', 'link_expire_time' => 'P7D'];
        $this->configurationService = new ConfigurationService();
        $this->configurationService->setConfig($config);

        $slugger = new AsciiSlugger();

        $this->fileSystemService = new FilesystemService($this->configurationService, $slugger);
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
        $bucket->setMaxRetentionDuration('P1Y');
        $bucket->setKey('v3fbdbyf2f0muqvl0t2mdixlteaxs45fsicrczavbec95fsr9rtx3x89fum1euir');
        $bucket->setIdentifier((string) Uuid::v4());
        $fileDataId = (string) Uuid::v4();
        $fileData = new FileData();
        $fileData->setIdentifier($fileDataId);
        $fileData->setFile($this->getExampleFile());
        $fileData->setBucket($bucket);

        $now = new \DateTimeImmutable('now');

        $fileData->setExistsUntil($now->add(new \DateInterval($bucket->getMaxRetentionDuration())));

        $fileDataSaved = $this->fileSystemService->saveFile($fileData);

        $fileDataGet = $this->fileSystemService->getLink($fileDataSaved, new PoliciesStruct());

        $ret = $this->fileSystemService->removeFile($fileData);
        $this->assertTrue($ret);

        // check dir empty
        $this->assertNull(FileOperations::isDirEmpty('testfile'));
    }
}
