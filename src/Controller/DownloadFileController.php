<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Controller;

use Dbp\Relay\BlobBundle\Service\BlobService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ShareLinkPersistenceService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DownloadFileController extends AbstractController
{
    /**
     * @var ShareLinkPersistenceService
     */
    private $shareLinkPersistenceService;

    /**
     * @var BlobService
     */
    private $blobService;

    public function __construct(ShareLinkPersistenceService $shareLinkPersistenceService, BlobService $blobService)
    {
        $this->shareLinkPersistenceService = $shareLinkPersistenceService;
        $this->blobService = $blobService;
    }

    public function index(string $identifier): Response
    {
        if (!$identifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'No identifier set', 'blobConnectorFilesystem:no-identifier-set');
        }

        $sharedLinkPersistence = $this->shareLinkPersistenceService->getShareLinkPersistence($identifier);
        if (!$sharedLinkPersistence) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'No file with this share id found', 'blobConnectorFilesystem:download-file');
        }

        // Check if sharelink is already invalid
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($now > $sharedLinkPersistence->getValidUntil()) {
            return $this->fileNotFoundResponse();
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

    public function fileNotFoundResponse(): Response
    {
        $loader = new FilesystemLoader(dirname(__FILE__).'/../Resources/views/');
        $twig = new Environment($loader);

        $template = $twig->load('fileNotFound.html.twig');
        $content = $template->render();

        $response = new Response();
        $response->setContent($content);

        return $response;
    }
}
