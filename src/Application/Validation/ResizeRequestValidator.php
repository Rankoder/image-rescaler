<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Domain\Enums\ImageFormat;
use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\ImageInspector;

/**
 * The one validation of a resize for every entry point; reports all invalid fields at once.
 */
final readonly class ResizeRequestValidator
{
    private const int MAX_FILE_BYTES = 5 * 1024 * 1024;
    private const int MAX_SOURCE_SIDE = 5000;
    private const int MIN_FRAME_SIDE = 40;
    private const int MAX_FRAME_SIDE = 5000;
    private const int MAX_OUTPUT_FILENAME_LENGTH = 100;

    public function __construct(private ImageInspector $inspector) {}

    /**
     * @throws ValidationFailed
     */
    public function validate(ResizeInput $input): ResizeRequest
    {
        $width = $this->frameSide(Field::MaxWidth, $input->maxWidth);
        $height = $this->frameSide(Field::MaxHeight, $input->maxHeight);
        $source = $this->source($input->file, is_int($width) ? $width : null, is_int($height) ? $height : null);
        $storage = $this->storage($input->service);
        $outputFilename = $this->outputFilename($input->outputFilename);

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

    /**
     * @return array{SourceFile, ImageFormat}|ValidationError
     */
    private function source(?SourceFile $file, ?int $frameWidth, ?int $frameHeight): array|ValidationError
    {
        if ($file === null) {
            return $this->fileError(ErrorCode::Required, 'The file is required.');
        }
        if ($file->problem !== null) {
            return $this->fileError($file->problem, $this->problemMessage($file->problem));
        }

        $bytes = $this->inspector->sizeInBytes($file->path);
        if ($bytes === 0) {
            return $this->fileError(ErrorCode::Empty, 'The file is empty.');
        }
        if ($bytes > self::MAX_FILE_BYTES) {
            return $this->fileError(ErrorCode::TooLarge, $this->problemMessage(ErrorCode::TooLarge));
        }

        $image = $this->inspector->inspect($file->path);
        $format = $image === null ? null : ImageFormat::tryFrom($image->mimeType);
        if ($image === null || $format === null) {
            return $this->fileError(ErrorCode::UnsupportedType, 'The file is not a JPEG, PNG or WebP image.');
        }

        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        if (!in_array($extension, $format->extensions(), true)) {
            return $this->fileError(ErrorCode::ExtensionMismatch, sprintf(
                'The file is a %s image, so its name must end with .%s.',
                $format->label(),
                implode(' or .', $format->extensions()),
            ));
        }

        if ($image->width > self::MAX_SOURCE_SIDE || $image->height > self::MAX_SOURCE_SIDE) {
            return $this->fileError(ErrorCode::DimensionsTooLarge, sprintf(
                'The image is %d × %d px; it can be at most %d × %d px.',
                $image->width,
                $image->height,
                self::MAX_SOURCE_SIDE,
                self::MAX_SOURCE_SIDE,
            ));
        }

        if ($frameWidth !== null && $frameHeight !== null && ($image->width < $frameWidth || $image->height < $frameHeight)) {
            return $this->fileError(ErrorCode::SmallerThanFrame, sprintf(
                'The image is %d × %d px; it must be at least %d × %d px to fill the frame.',
                $image->width,
                $image->height,
                $frameWidth,
                $frameHeight,
            ));
        }

        return [$file, $format];
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

    private function outputFilename(?string $value): string|ValidationError|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9_-]+\z/', $value) !== 1) {
            return new ValidationError(
                Field::OutputFilename,
                ErrorCode::InvalidCharacters,
                'output_filename may hold only letters, digits, - and _.',
            );
        }
        if (strlen($value) > self::MAX_OUTPUT_FILENAME_LENGTH) {
            return new ValidationError(
                Field::OutputFilename,
                ErrorCode::TooLong,
                sprintf('output_filename can be at most %d characters long.', self::MAX_OUTPUT_FILENAME_LENGTH),
            );
        }

        return $value;
    }

    private function fileError(ErrorCode $code, string $message): ValidationError
    {
        return new ValidationError(Field::File, $code, $message);
    }

    private function problemMessage(ErrorCode $problem): string
    {
        return match ($problem) {
            ErrorCode::TooLarge => 'The file is larger than 5 MB.',
            ErrorCode::NotFound => 'The file does not exist in the input directory.',
            ErrorCode::OutsideInputDirectory => 'The file must be inside the input directory.',
            default => 'The file cannot be read.',
        };
    }
}
