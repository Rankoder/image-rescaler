<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\ImageFileRules;
use App\Application\Validation\NameRule;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationError;
use App\Application\Validation\ValidationFailed;
use App\Domain\Interfaces\ImageLibrary;
use App\Domain\Interfaces\ImageReencoder;

/**
 * The images in the input directory: listing them and adding new ones under the same rules as an upload.
 */
final readonly class InputImages
{
    public function __construct(
        private ImageLibrary $library,
        private ImageFileRules $fileRules,
        private NameRule $names,
        private ImageReencoder $reencoder,
    ) {}

    /**
     * @return list<string>
     */
    public function list(): array
    {
        return $this->library->names();
    }

    /**
     * @return string the name the image is stored under
     * @throws ValidationFailed
     */
    public function add(?SourceFile $file, ?string $name): string
    {
        $source = $this->fileRules->check($file);
        $chosenName = $this->names->check(Field::Name, $name);

        if ($source instanceof ValidationError || $chosenName instanceof ValidationError) {
            throw new ValidationFailed(array_values(array_filter(
                [$source, $chosenName],
                static fn(mixed $result): bool => $result instanceof ValidationError,
            )));
        }

        [$sourceFile, $format] = $source;
        $baseName = $chosenName ?? $this->names->safeVersionOf(pathinfo($sourceFile->name, PATHINFO_FILENAME));
        $filename = $baseName . '.' . $format->extension();

        $reencodedPath = $this->reencoder->reencode($sourceFile->path, $format);
        try {
            if (!$this->library->addIfAbsent($reencodedPath, $filename)) {
                throw new ValidationFailed([new ValidationError(
                    Field::Name,
                    ErrorCode::AlreadyExists,
                    sprintf('An image named %s is in the input directory already.', $filename),
                )]);
            }
        } finally {
            if (is_file($reencodedPath)) {
                unlink($reencodedPath);
            }
        }

        return $filename;
    }
}
