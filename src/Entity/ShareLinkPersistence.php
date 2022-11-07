<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="blob_connector_filesystem")
 */
class ShareLinkPersistence
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=50, unique=true)
     */
    private $identifier;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(type="datetime_immutable")
     */
    private $validUntil;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $link;

    /**
     * @var string
     * @ORM\Column(name="file_data_identifier", type="string")
     */
    private $fileDataIdentifier;

    /**
     * @var string
     * @ORM\Column(name="filesystem_path", type="string")
     */
    private $filesystemPath;

    /**
     * @return mixed
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param mixed $identifier
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getValidUntil(): \DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    public function getFileDataIdentifier(): string
    {
        return $this->fileDataIdentifier;
    }

    public function setFileDataIdentifier(string $fileDataIdentifier): void
    {
        $this->fileDataIdentifier = $fileDataIdentifier;
    }

    public function getFilesystemPath(): string
    {
        return $this->filesystemPath;
    }

    public function setFilesystemPath(string $filesystemPath): void
    {
        $this->filesystemPath = $filesystemPath;
    }
}
