<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\ImageLibrary;
use App\Domain\ValueObjects\ImageName;

/**
 * The one validation of a resize for every entry point; reports all invalid fields at once.
 */
final readonly class ResizeRequestValidator
{
    private const int MIN_FRAME_SIDE = 40;
    private const int MAX_FRAME_SIDE = 5000;

    public function __construct(
        private ImageFileRules $fileRules,
        private NameRule $names,
        private ImageLibrary $library,
    ) {}

    /**
     * @throws ValidationFailed
     */
    public function validate(ResizeInput $input): ResizeRequest
    {
        $width = $this->frameSide(Field::MaxWidth, $input->maxWidth);
        $height = $this->frameSide(Field::MaxHeight, $input->maxHeight);
        $source = $this->sourceOf($input);
        if (!$source instanceof ValidationError) {
            $source = $this->fileRules->check($source, is_int($width) ? $width : null, is_int($height) ? $height : null);
        }
        $storage = $this->storage($input->service);
        $outputFilename = $this->names->check(Field::OutputFilename, $input->outputFilename);

        if (
            $source instanceof ValidationError
            || $width instanceof ValidationError
            || $height instanceof ValidationError
            || $storage instanceof ValidationError
            || $outputFilename instanceof ValidationError
        ) {
            $results = [$source, $width, $height, $storage, $outputFilename];
            throw new ValidationFailed(array_values(array_filter(
                $results,
                static fn(mixed $result): bool => $result instanceof ValidationError,
            )));
        }

        [$file, $format] = $source;

        return new ResizeRequest($file->path, $file->name, $format, $width, $height, $storage, $outputFilename);
    }

    private function sourceOf(ResizeInput $input): SourceFile|ValidationError|null
    {
        $image = $input->image === '' ? null : $input->image;
        if ($image === null) {
            return $input->file;
        }
        if ($input->file !== null) {
            return new ValidationError(Field::Image, ErrorCode::ConflictsWithFile, 'Give either file or image, not both.');
        }

        // Only a plain image name counts; ../, absolute paths and hidden files are simply not found
        $imageName = ImageName::tryFrom($image);
        $path = $imageName === null ? null : $this->library->path($imageName->value);

        return $path === null
            ? SourceFile::rejected(ErrorCode::NotFound, $image, Field::Image)
            : SourceFile::at($path, $image, Field::Image);
    }

    private function frameSide(Field $field, ?string $value): int|ValidationError
    {
        if ($value === null || $value === '') {
            return new ValidationError($field, ErrorCode::Required, sprintf('%s is required.', $field->value));
        }
        if (preg_match('/^\d+\z/', $value) !== 1) {
            return new ValidationError($field, ErrorCode::NotInteger, sprintf('%s must be a whole number.', $field->value));
        }

        $side = (int) $value;
        if ($side < self::MIN_FRAME_SIDE || $side > self::MAX_FRAME_SIDE) {
            return new ValidationError($field, ErrorCode::OutOfRange, sprintf(
                '%s must be between %d and %d.',
                $field->value,
                self::MIN_FRAME_SIDE,
                self::MAX_FRAME_SIDE,
            ));
        }

        return $side;
    }

    private function storage(?string $value): StorageType|ValidationError
    {
        if ($value === null || $value === '') {
            return StorageType::Local;
        }

        return StorageType::tryFrom($value) ?? new ValidationError(
            Field::Service,
            ErrorCode::Unsupported,
            sprintf('service must be one of: %s.', implode(', ', array_column(StorageType::cases(), 'value'))),
        );
    }
}
