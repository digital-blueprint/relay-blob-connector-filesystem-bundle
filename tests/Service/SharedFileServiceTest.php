<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Tests\Service;

use Dbp\Relay\BlobConnectorFilesystemBundle\Entity\ShareLinkPersistence;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\SharedFileService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class SharedFileServiceTest extends WebTestCase
{
    private $api;

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

        $this->api = new SharedFileService($em);
    }

    public function testCreateSharedLinkPersistence()
    {
        $shareLinkId = (string) Uuid::v4();
        $shareLinkPersistence = new ShareLinkPersistence();
        $shareLinkPersistence->setIdentifier($shareLinkId);
        $shareLinkPersistence->setFilesystemPath('mypath/my-subpath');
        $shareLinkPersistence->setFileDataIdentifier('someid');
        $shareLinkPersistence->setLink('mylink');
        $shareLinkPersistence->setValidUntil(new \DateTimeImmutable('now'));

        $this->api->saveShareLinkPersistence($shareLinkPersistence);

        $this->assertCount(1, $this->api->getAllShareLinkPersistencesByFileDataID('someid'));
        $this->assertSame('mylink', $this->api->getAllShareLinkPersistencesByFileDataID('someid')[0]->getLink());
    }

    public function testGetShareLinkPersistence()
    {
        $shareLinkId = (string) Uuid::v4();
        $shareLinkPersistence = new ShareLinkPersistence();
        $shareLinkPersistence->setIdentifier($shareLinkId);
        $shareLinkPersistence->setFilesystemPath('mypath/my-subpath');
        $shareLinkPersistence->setFileDataIdentifier('someid');
        $shareLinkPersistence->setLink('mylink');
        $shareLinkPersistence->setValidUntil(new \DateTimeImmutable('now'));

        $this->api->saveShareLinkPersistence($shareLinkPersistence);

        $id = $this->api->getAllShareLinkPersistencesByFileDataID('someid')[0]->getIdentifier();
        $shareLinkPersistence = $this->api->getShareLinkPersistence($id);
        $this->assertSame($shareLinkPersistence->getIdentifier(), $id);
    }
}
