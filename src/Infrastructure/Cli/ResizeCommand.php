<?php

declare(strict_types=1);

namespace App\Infrastructure\Cli;

use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationFailed;
use Exception;

final readonly class ResizeCommand
{
    public const string USAGE = 'Usage: bin/resize -f <file> -w <max_width> -h <max_height> [-s <service>] [-o <output_filename>]';

    private const array OPTIONS = [
        Field::File->value => 'f',
        Field::MaxWidth->value => 'w',
        Field::MaxHeight->value => 'h',
        Field::Service->value => 's',
        Field::OutputFilename->value => 'o',
    ];

    public function __construct(private ResizeAndStoreImage $resizeAndStoreImage) {}

    /**
     * @param array<string, mixed> $options as returned by getopt('f:w:h:s:o:')
     * @param resource $output
     * @param resource $errorOutput
     */
    public function run(array $options, $output, $errorOutput): int
    {
        $option = static fn(string $name): ?string => is_string($options[$name] ?? null) ? $options[$name] : null;

        try {
            $stored = $this->resizeAndStoreImage->handle(new ResizeInput(
                $this->sourceFile($option('f')),
                $option('w'),
                $option('h'),
                $option('s'),
                $option('o'),
            ));
        } catch (ValidationFailed $failure) {
            foreach ($failure->errors as $error) {
                fwrite($errorOutput, sprintf("-%s: %s\n", self::OPTIONS[$error->field->value], $error->message));
            }
            fwrite($errorOutput, self::USAGE . "\n");

            return 1;
        } catch (Exception $exception) {
            fwrite($errorOutput, 'Error: ' . $exception->getMessage() . "\n");

            return 1;
        }

        fwrite($output, sprintf("Stored %s\n", $stored->path));

        return 0;
    }

    private function sourceFile(?string $path): ?SourceFile
    {
        if ($path === null || $path === '') {
            return null;
        }

        return is_file($path) ? SourceFile::at($path, basename($path)) : SourceFile::rejected(ErrorCode::NotFound, basename($path));
    }
}
