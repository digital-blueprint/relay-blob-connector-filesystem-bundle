<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Controller;

use Dbp\Relay\BlobBundle\Service\BlobService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\SharedFileService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DownloadFileController extends AbstractController
{
    /**
     * @var SharedFileService
     */
    private $sharedFileService;

    /**
     * @var BlobService
     */
    private $blobService;

    public function __construct(SharedFileService $sharedFileService, BlobService $blobService)
    {
        $this->sharedFileService = $sharedFileService;
        $this->blobService = $blobService;
    }

    public function __invoke(string $identifier): Response
    {
        $sharedLinkPersistence = $this->sharedFileService->getSharedFile($identifier);
        if (!$sharedLinkPersistence) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'No file wat this share id found', 'blobConnectorFilesystem:download-file');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($now > $sharedLinkPersistence->getValidUntil()) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Sharelink is not valid anymore', 'blobConnectorFilesystem:sharelink-invalid');
        }

        $response = new BinaryFileResponse($sharedLinkPersistence->getFilesystemPath());

        $filename = $this->blobService->getFileData($sharedLinkPersistence->getFileDataIdentifier())->getFileName();

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        return $response;
    }
}
