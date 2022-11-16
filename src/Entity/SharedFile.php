<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Serializer\Annotation\Groups;

class SharedFile
{
    /**
     * @ApiProperty(identifier=true)
     */
    private $identifier;

    /**
     * @var ShareLinkPersistence
     */
    private $shareLink; // TODO dont know if we need this here

    /**
     * @Groups({"BlobConnectorFilesystem:output"})
     *
     * @var BinaryFileResponse
     */
    private $binaryFileResponse;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getShareLink(): ShareLinkPersistence
    {
        return $this->shareLink;
    }

    public function setShareLink(ShareLinkPersistence $shareLink): void
    {
        $this->shareLink = $shareLink;
    }

    public function getBinaryFileResponse(): BinaryFileResponse
    {
        return $this->binaryFileResponse;
    }

    public function setBinaryFileResponse($binaryFileResponse): void
    {
        $this->binaryFileResponse = $binaryFileResponse;
    }
}
