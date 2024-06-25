<?php

declare(strict_types=1);

namespace App\Infrastructure\Controllers;

use App\Application\Services\ImageProcessingService;
use App\Application\Services\StorageServiceFactory;
use App\Application\Services\ImageValidationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Dotenv\Dotenv;

/**
 * ImageController handles the image upload, validation, processing, and storage.
 * It utilizes various services to achieve this functionality.
 */
class ImageController
{
    private Psr17Factory $psr17Factory;
    private ImageProcessingService $imageProcessingService;
    private StorageServiceFactory $storageServiceFactory;
    private ImageValidationService $imageValidationService;

    public function __construct()
    {
        $this->psr17Factory = new Psr17Factory();
        $this->imageProcessingService = new ImageProcessingService();
        $this->storageServiceFactory = new StorageServiceFactory();
        $this->imageValidationService = new ImageValidationService();

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();
    }

    /**
     * Handles the image upload and resizing.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        $file = $uploadedFiles['file'] ?? null;
        $service = $parsedBody['service'] ?? 'local';
        $maxWidth = (int)($parsedBody['max_width'] ?? 0);
        $maxHeight = (int)($parsedBody['max_height'] ?? 0);
        $outputFilename = $parsedBody['output_filename'] ?? '';

        if (!$this->isValidRequest($file, $maxWidth, $maxHeight)) {
            return $this->createErrorResponse('Invalid request parameters', 400);
        }

        $fileArray = $this->getFileArray($file);

        try {
            $this->imageValidationService->validate($fileArray, $maxWidth, $maxHeight);

            $uniqueFilename = $this->imageProcessingService->generateUniqueFilename($fileArray['name'], $outputFilename);
            $imageValueObject = $this->imageProcessingService->resizeImage($fileArray, $maxWidth, $maxHeight, $uniqueFilename);

            $storageService = $this->storageServiceFactory->create($service);
            $uploadedFilePath = $storageService->upload($imageValueObject);
            $uploadedFileUrl = $storageService->getPublicUrl($uploadedFilePath);

            return $this->createSuccessResponse($uploadedFilePath, $uploadedFileUrl);
        } catch (\Exception $e) {
            return $this->createErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Validates the request parameters.
     *
     * @param mixed $file
     * @param int $maxWidth
     * @param int $maxHeight
     * @return bool
     */
    private function isValidRequest($file, int $maxWidth, int $maxHeight): bool
    {
        return $file !== null && $maxWidth > 0 && $maxHeight > 0;
    }

    /**
     * Converts the uploaded file to an array.
     *
     * @param mixed $file
     * @return array
     */
    private function getFileArray($file): array
    {
        return [
            'tmp_name' => $file->getStream()->getMetadata('uri'),
            'name' => $file->getClientFilename(),
        ];
    }

    /**
     * Creates an error response.
     *
     * @param string $message
     * @param int $statusCode
     * @return ResponseInterface
     */
    private function createErrorResponse(string $message, int $statusCode): ResponseInterface
    {
        $responseBody = json_encode(['status' => 'error', 'message' => $message]);
        return $this->psr17Factory->createResponse($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17Factory->createStream($responseBody));
    }

    /**
     * Creates a success response.
     *
     * @param string $uploadedFilePath
     * @param string $uploadedFileUrl
     * @return ResponseInterface
     */
    private function createSuccessResponse(string $uploadedFilePath, string $uploadedFileUrl): ResponseInterface
    {
        $responseBody = json_encode([
            'status' => 'success',
            'path' => $uploadedFilePath,
            'url' => $uploadedFileUrl,
        ]);

        return $this->psr17Factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17Factory->createStream($responseBody));
    }
}
