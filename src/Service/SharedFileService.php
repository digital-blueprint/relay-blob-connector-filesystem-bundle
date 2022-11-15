<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\BlobConnectorFilesystemBundle\Entity\ShareLinkPersistence;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

class SharedFileService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $manager = $managerRegistry->getManager('dbp_relay_blob_connector_filesystem_bundle');
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;
    }

    public function checkConnection()
    {
        $this->em->getConnection()->connect();
    }

    public function getSharedFile(string $identifier): ShareLinkPersistence
    {
        $sharedLinkPersistence = $this->em
            ->getRepository(ShareLinkPersistence::class)
            ->find($identifier);

        if (!$sharedLinkPersistence) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Fileshare was not found!', 'blob-connector-filesystem:fileshare-not-found');
        }

        return $sharedLinkPersistence;
    }

    public function getAllSharedFiles(string $identifier): array
    {
        $sharedLinkPersistences = $this->em
            ->getRepository(ShareLinkPersistence::class)
            ->findBy(['fileDataIdentifier' => $identifier]);

        return $sharedLinkPersistences;
    }

    public function removeShareLinkPersistence(ShareLinkPersistence $shareLinkPersistence)
    {
        $this->em->remove($shareLinkPersistence);
        $this->em->flush();
    }

    public function removeShareLinkPersistences(array $shareLinkPersistences)
    {
        foreach ($shareLinkPersistences as $shareLinkPersistence) {
            $this->removeShareLinkPersistence($shareLinkPersistence);
        }
    }

    public function removeShareLinkPersistencesByFileDataID(string $identifier)
    {
        $sharedFiles = $this->getAllSharedFiles($identifier);
        dump($sharedFiles);
        $this->removeShareLinkPersistences($sharedFiles);
    }
}
