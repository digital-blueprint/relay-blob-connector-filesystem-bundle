<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Entity;

date_default_timezone_set('UTC');

use Doctrine\ORM\Mapping as ORM;

class ShareLinkPersistence
{

    private $identifier;

    /**
     * @var \DateTimeImmutable
     */
    private $validUntil;

    /**
     * @var string
     */
    private $link;

    /**
     * @var string
     */
    private $fileDataIdentifier;

    /**
     * @var string
     */
    private $filesystemPath;

    /**
     * @var string
     */
    private $signature;

    public function getIdentifier()
    {
        return $this->identifier;
    }

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

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): void
    {
        $this->signature = $signature;
    }
}
