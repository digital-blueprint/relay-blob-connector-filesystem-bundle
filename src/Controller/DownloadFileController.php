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
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;

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

    public function index(string $identifier): Response
    {
        if (!$identifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'No identifier set', 'blobConnectorFilesystem:no-identifier-set');
        }

        $sharedLinkPersistence = $this->sharedFileService->getSharedFile($identifier);
        if (!$sharedLinkPersistence) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'No file wat this share id found', 'blobConnectorFilesystem:download-file');
        }

        // Check if sharelink is already invalid
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($now > $sharedLinkPersistence->getValidUntil()) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Sharelink is not valid anymore', 'blobConnectorFilesystem:sharelink-invalid');
        }

        $filePath = $sharedLinkPersistence->getFilesystemPath();

        $response = new BinaryFileResponse($filePath);

        $mimeTypeGuesser = new FileinfoMimeTypeGuesser();

        // Set the mimetype with the guesser or manually
        if ($mimeTypeGuesser->isGuesserSupported()) {
            // Guess the mimetype of the file according to the extension of the file
            $response->headers->set('Content-Type', $mimeTypeGuesser->guessMimeType($filePath));
        } else {
            // Set the mimetype of the file manually, in this case for a text file is text/plain
            $response->headers->set('Content-Type', 'text/plain');
        }

        $filename = $this->blobService->getFileData($sharedLinkPersistence->getFileDataIdentifier())->getFileName();

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        return $response;
    }
}
