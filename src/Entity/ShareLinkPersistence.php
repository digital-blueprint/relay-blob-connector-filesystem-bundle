<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorLocalBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Dbp\Relay\BlobBundle\Entity\FileData;

/**
 * @ORM\Entity
 * @ORM\Table(name="blob_connector_local")
 */
class ShareLinkPersistence
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
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
}
