<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Domain\Enums\ImageFormat;
use App\Domain\Interfaces\ImageInspector;

/**
 * The rules every source image follows, uploaded or taken from the input directory: type from the
 * content matching the extension, size and dimensions, and with a frame, at least the frame's size.
 */
final readonly class ImageFileRules
{
    private const int MAX_FILE_BYTES = 5 * 1024 * 1024;
    private const int MAX_SOURCE_SIDE = 5000;

    public function __construct(private ImageInspector $inspector) {}

    /**
     * @return array{SourceFile, ImageFormat}|ValidationError
     */
    public function check(?SourceFile $file, ?int $frameWidth = null, ?int $frameHeight = null): array|ValidationError
    {
        if ($file === null) {
            return new ValidationError(Field::File, ErrorCode::Required, 'The file is required.');
        }
        $error = static fn(ErrorCode $code, string $message): ValidationError => new ValidationError($file->field, $code, $message);

        if ($file->problem !== null) {
            return $error($file->problem, $this->problemMessage($file->problem));
        }

        $bytes = $this->inspector->sizeInBytes($file->path);
        if ($bytes === 0) {
            return $error(ErrorCode::Empty, 'The file is empty.');
        }
        if ($bytes > self::MAX_FILE_BYTES) {
            return $error(ErrorCode::TooLarge, $this->problemMessage(ErrorCode::TooLarge));
        }

        $image = $this->inspector->inspect($file->path);
        $format = $image === null ? null : ImageFormat::tryFrom($image->mimeType);
        if ($image === null || $format === null) {
            return $error(ErrorCode::UnsupportedType, 'The file is not a JPEG, PNG or WebP image.');
        }

        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        if (!in_array($extension, $format->extensions(), true)) {
            return $error(ErrorCode::ExtensionMismatch, sprintf(
                'The file is a %s image, so its name must end with .%s.',
                $format->label(),
                implode(' or .', $format->extensions()),
            ));
        }

        if ($image->width > self::MAX_SOURCE_SIDE || $image->height > self::MAX_SOURCE_SIDE) {
            return $error(ErrorCode::DimensionsTooLarge, sprintf(
                'The image is %d × %d px; it can be at most %d × %d px.',
                $image->width,
                $image->height,
                self::MAX_SOURCE_SIDE,
                self::MAX_SOURCE_SIDE,
            ));
        }

        if ($frameWidth !== null && $frameHeight !== null && ($image->width < $frameWidth || $image->height < $frameHeight)) {
            return $error(ErrorCode::SmallerThanFrame, sprintf(
                'The image is %d × %d px; it must be at least %d × %d px to fill the frame.',
                $image->width,
                $image->height,
                $frameWidth,
                $frameHeight,
            ));
        }

        return [$file, $format];
    }

    private function problemMessage(ErrorCode $problem): string
    {
        return match ($problem) {
            ErrorCode::TooLarge => 'The file is larger than 5 MB.',
            ErrorCode::NotFound => 'The image does not exist in the input directory.',
            ErrorCode::OutsideInputDirectory => 'The file must be inside the input directory.',
            default => 'The file cannot be read.',
        };
    }
}
