<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Dbp\Relay\BlobConnectorFilesystemBundle\Entity\ShareLinkPersistence;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\SharedFileService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Controller\DownloadFileController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={},
 *     itemOperations={
 *         "get" = {
 *             "method" = "GET",
 *             "security" = "is_granted('IS_AUTHENTICATED_FULLY')",
 *             "path" = "/blob/filesystem/{identifier}",
 *             "controller" = DownloadFileController::class,
 *             "read" = false,
 *             "openapi_context" = {
 *                 "tags" = {"Blob"},
 *                 "summary" = "Download a specific file from a file share link",
 *             }
 *         },
 *     },
 *     iri="https://schema.org/Entity",
 *     shortName="BlobConnectorFilesystem",
 *     normalizationContext={
 *         "groups" = {"BlobConnectorFilesystem:output"},
 *         "jsonld_embed_context" = true
 *     }
 * )
 */
class SharedFile
{
    /**
     * @ApiProperty(identifier=true)
     */
    private $identifier;

    /**
     * @var ShareLinkPersistence
     */
    private $shareLink; // dont know if we need this here


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

    /**
     * @return ShareLinkPersistence
     */
    public function getShareLink(): ShareLinkPersistence
    {
        return $this->shareLink;
    }

    /**
     * @param ShareLinkPersistence $shareLink
     */
    public function setShareLink(ShareLinkPersistence $shareLink): void
    {
        $this->shareLink = $shareLink;
    }

    /**
     * @return BinaryFileResponse
     */
    public function getBinaryFileResponse(): BinaryFileResponse
    {
        return $this->binaryFileResponse;
    }

    /**
     * @param BinaryFileResponse $binaryFileResponse
     */
    public function setBinaryFileResponse($binaryFileResponse): void
    {
        $this->binaryFileResponse = $binaryFileResponse;
    }
}
