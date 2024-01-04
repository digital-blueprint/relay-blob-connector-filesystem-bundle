<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Controller;

use Dbp\Relay\BlobBundle\Helper\DenyAccessUnlessCheckSignature;
use Dbp\Relay\BlobBundle\Service\BlobService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DownloadFileController extends AbstractController
{
    /**
     * @var BlobService
     */
    private $blobService;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    public function __construct(BlobService $blobService, ConfigurationService $configurationService)
    {
        $this->blobService = $blobService;
        $this->configurationService = $configurationService;
    }

    /**
     * @Route("/blob/filesystem/{identifier}", name="blob_filesystem_connector_file_download")
     */
    public function __invoke(Request $request, string $identifier): Response
    {
        if (!$identifier) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'No identifier set', 'blobConnectorFilesystem:no-identifier-set');
        }

        // get necessary params
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $fileData = $this->blobService->getFileData($identifier);
        $this->blobService->setBucket($fileData);

        /** @var string */
        $validUntil = $request->query->get('validUntil', '');
        assert(is_string($validUntil));
        $validUntil = urldecode($validUntil);

        /** @var string */
        $sig = $request->query->get('sig', '');

        // Check link for validity
        if (!$validUntil) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'validUntil missing', 'blob-connector-filesystem:missing-valid-until');
        }

        if (!$sig) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'signature missing', 'blob-connector-filesystem:missing-sig');
        }

        assert(!is_null($sig));
        assert(is_string($sig));

        assert(!is_null($validUntil));
        assert(is_string($validUntil));

        // check signature
        DenyAccessUnlessCheckSignature::verifyChecksumAndSignature($fileData->getBucket()->getKey(), $sig, $request);

        // check if validUntil is a valid datetime
        try {
            $validUntil = new \DateTimeImmutable($validUntil);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'validUntil date format malformed', 'blob-connector-filesystem:malformed-valid-until');
        }

        // check if file is expired or got deleted
        if ($now > $validUntil) {
            dump('link expired!');

            return $this->fileNotFoundResponse();
        }

        // check if file is expired or got deleted
        if ($now > $fileData->getExistsUntil() || !file_exists($this->getPath($fileData))) {
            dump('file '.$this->getPath($fileData).' NOT found');

            return $this->fileNotFoundResponse();
        }

        /** @var string */
        $filePath = $this->getPath($fileData);

        // build binary response
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
        $response->setStatusCode(400);

        return $response;
    }

    public function getPath($fileData): string
    {
        return $this->configurationService->getPath().'/'.substr($fileData->getIdentifier(), 0, 2).'/'.$fileData->getIdentifier();
    }
}
