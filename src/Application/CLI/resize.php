<?php

declare(strict_types=1);

namespace App\Application\CLI;

require __DIR__ . '/../../../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Application\Services\StorageServiceFactory;
use App\Application\Services\ImageProcessingService;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\ResizeRequestValidator;
use App\Application\Validation\SourceFile;
use App\Infrastructure\Image\LocalImageInspector;

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
    $option = static fn(string $name): ?string => is_string($options[$name] ?? null) ? $options[$name] : null;
    $filePath = $option('f') ?? '';

    $resizeAndStoreImage = new ResizeAndStoreImage(
        new ResizeRequestValidator(new LocalImageInspector()),
        new ImageProcessingService(),
        new StorageServiceFactory(),
    );

    return $resizeAndStoreImage->handle(new ResizeInput(
        SourceFile::at($filePath, basename($filePath)),
        $option('w'),
        $option('h'),
        $option('s'),
        $option('o'),
    ))->path;
}

// Run the script
main();
