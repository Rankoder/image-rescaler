<?php

declare(strict_types=1);

namespace App\Infrastructure\Cli;

use App\Application\Services\ResizeAndStoreImage;
use App\Application\Validation\Field;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationFailed;
use App\Infrastructure\Filesystem\InputDirectory;
use Exception;

final readonly class ResizeCommand
{
    public const string USAGE = 'Usage: bin/resize -f <file in the input directory> -w <max_width> -h <max_height> [-s <service>] [-o <output_filename>]';

    public function __construct(
        private ResizeAndStoreImage $resizeAndStoreImage,
        private InputDirectory $inputDirectory,
    ) {}

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
                fwrite($errorOutput, sprintf("%s: %s\n", $this->option($error->field), $error->message));
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

    private function sourceFile(?string $name): ?SourceFile
    {
        return $name === null || $name === '' ? null : $this->inputDirectory->sourceFile($name);
    }

    private function option(Field $field): string
    {
        return match ($field) {
            Field::File, Field::Image => '-f',
            Field::MaxWidth => '-w',
            Field::MaxHeight => '-h',
            Field::Service => '-s',
            Field::OutputFilename, Field::Name => '-o',
        };
    }
}
