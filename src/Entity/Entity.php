<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorLocalBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Dbp\Relay\BlobConnectorLocalBundle\Controller\LoggedInOnly;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={
 *         "get" = {
 *             "path" = "/blob-connector-local/entitys",
 *             "openapi_context" = {
 *                 "tags" = {"Blob Connector local datasystem"},
 *             },
 *         }
 *     },
 *     itemOperations={
 *         "get" = {
 *             "path" = "/blob-connector-local/entitys/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Blob Connector local datasystem"},
 *             },
 *         },
 *         "put" = {
 *             "path" = "/blob-connector-local/entitys/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Blob Connector local datasystem"},
 *             },
 *         },
 *         "delete" = {
 *             "path" = "/blob-connector-local/entitys/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Blob Connector local datasystem"},
 *             },
 *         },
 *         "loggedin_only" = {
 *             "security" = "is_granted('IS_AUTHENTICATED_FULLY')",
 *             "method" = "GET",
 *             "path" = "/blob-connector-local/entitys/{identifier}/loggedin-only",
 *             "controller" = LoggedInOnly::class,
 *             "openapi_context" = {
 *                 "summary" = "Only works when logged in.",
 *                 "tags" = {"Blob Connector local datasystem"},
 *             },
 *         }
 *     },
 *     iri="https://schema.org/Entity",
 *     shortName="BlobConnectorLocalEntity",
 *     normalizationContext={
 *         "groups" = {"BlobConnectorLocalEntity:output"},
 *         "jsonld_embed_context" = true
 *     },
 *     denormalizationContext={
 *         "groups" = {"BlobConnectorLocalEntity:input"},
 *         "jsonld_embed_context" = true
 *     }
 * )
 */
class Entity
{
    /**
     * @ApiProperty(identifier=true)
     */
    private $identifier;

    /**
     * @ApiProperty(iri="https://schema.org/name")
     * @Groups({"BlobConnectorLocalEntity:output", "BlobConnectorLocalEntity:input"})
     *
     * @var string
     */
    private $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }
}
