<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Enums\ImageFormat;
use InvalidArgumentException;

/**
 * Plain name of an image in the input directory: safe base name and a supported format's extension.
 */
final readonly class ImageName
{
    public string $value;

    public function __construct(string $value)
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid image name "%s".', $value));
        }

        $this->value = $value;
    }

    public static function tryFrom(string $value): ?self
    {
        return self::isValid($value) ? new self($value) : null;
    }

    public static function isValid(string $value): bool
    {
        $extensions = array_merge(...array_map(
            static fn(ImageFormat $format): array => $format->extensions(),
            ImageFormat::cases(),
        ));

        return preg_match(sprintf('/^[A-Za-z0-9_-]{1,100}\.(%s)\z/', implode('|', $extensions)), $value) === 1;
    }
}
