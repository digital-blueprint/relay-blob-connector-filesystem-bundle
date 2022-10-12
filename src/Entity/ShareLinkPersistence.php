<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorLocalBundle\Entity;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Doctrine\ORM\Mapping as ORM;

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
     * @var \DateTime
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validUntil;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $link;

    /**
     * @var string
     * @ORM\Column(type="string")
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

    /**
     * @return \DateTime
     */
    public function getValidUntil(): \DateTime
    {
        return $this->validUntil;
    }

    /**
     * @param \DateTime $validUntil
     */
    public function setValidUntil(\DateTime $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * @param string $link
     */
    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    /**
     * @return string
     */
    public function getFileDataIdentifier(): string
    {
        return $this->fileDataIdentifier;
    }

    /**
     * @param string $fileDataIdentifier
     */
    public function setFileDataIdentifier(string $fileDataIdentifier): void
    {
        $this->fileDataIdentifier = $fileDataIdentifier;
    }
}
