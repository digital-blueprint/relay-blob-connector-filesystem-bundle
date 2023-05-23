<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Controller;

use Dbp\Relay\BlobBundle\Service\BlobService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use function PHPUnit\Framework\throwException;


class DownloadFileController extends AbstractController
{
    /**
     * @var BlobService
     */
    private $blobService;

    public function __construct(BlobService $blobService)
    {
        $this->blobService = $blobService;
    }

    public function index(Request $request, string $identifier): Response
    {
        if (!$identifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'No identifier set', 'blobConnectorFilesystem:no-identifier-set');
        }

        // Check if sharelink is already invalid
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $fileData = $this->blobService->getFileData($identifier);
        $this->blobService->setBucket($fileData);

        if ($now > $fileData->getExistsUntil()) {
            dump("file NOT found");
            return $this->fileNotFoundResponse();
        }

        if(hash('sha256', $request->getPathInfo().'?'.'validUntil='.str_replace(" ", "+", $request->query->get('validUntil', '')).'&path='.$request->query->get('path', '').$fileData->getBucket()->getPublicKey()) !== $request->query->get('checksum', '') || $request->query->get('checksum', '') !== $this->blobService->getChecksum($fileData)) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, 'Checksum is not the same!', 'blob:bad-checksum');
        }

        /** @var string */
        $filePath = $request->query->get('path', '');

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

        $filename = $fileData->getFileName();

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
