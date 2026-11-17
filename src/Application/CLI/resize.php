<?php

declare(strict_types=1);

namespace App\Application\CLI;

require __DIR__ . '/../../../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Application\Services\StorageServiceFactory;
use App\Application\Services\ImageProcessingService;

/**
 * Main function to handle the image resizing and upload.
 */
function main(): void
{
    loadEnvironmentVariables();

    $options = getCommandLineOptions();
    if (!validateOptions($options)) {
        displayUsage();
        exit(1);
    }

    try {
        $uploadedFilePath = resizeAndUploadImage($options);
        echo "File uploaded successfully: $uploadedFilePath\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

/**
 * Load environment variables from .env file.
 */
function loadEnvironmentVariables(): void
{
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
    $dotenv->load();
}

/**
 * Get and parse command line options.
 *
 * @return array
 */
function getCommandLineOptions(): array
{
    return getopt("f:s:w:h:o:");
}

/**
 * Validate the command line options.
 *
 * @param array $options
 * @return bool
 */
function validateOptions(array $options): bool
{
    return isset($options['f'], $options['w'], $options['h']);
}

/**
 * Display usage instructions.
 */
function displayUsage(): void
{
    echo "Usage: php resize.php -f <file_path> -s <service> -w <max_width> -h <max_height> [-o <output_filename>]\n";
}

/**
 * Resize and upload the image.
 *
 * @param array $options
 * @return string
 * @throws \Exception
 */
function resizeAndUploadImage(array $options): string
{
    $filePath = $options['f'];
    $service = $options['s'] ?? 'local';
    $maxWidth = (int) $options['w'];
    $maxHeight = (int) $options['h'];
    $outputFilename = $options['o'] ?? '';

    $imageProcessingService = new ImageProcessingService();
    $versionedFilename = $imageProcessingService->generateUniqueFilename(basename($filePath), $outputFilename);
    $image = $imageProcessingService->resizeImage(['tmp_name' => $filePath, 'name' => basename($filePath)], $maxWidth, $maxHeight, $versionedFilename);

    $storageService = StorageServiceFactory::create($service);
    return $storageService->upload($image);
}

// Run the script
main();
