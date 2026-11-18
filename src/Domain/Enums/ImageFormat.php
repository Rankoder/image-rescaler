<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum ImageFormat: string
{
    case Jpeg = 'image/jpeg';
    case Png = 'image/png';
    case Webp = 'image/webp';

    public function extension(): string
    {
        return $this->extensions()[0];
    }

    /**
     * @return non-empty-list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Jpeg => ['jpg', 'jpeg'],
            self::Png => ['png'],
            self::Webp => ['webp'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Jpeg => 'JPEG',
            self::Png => 'PNG',
            self::Webp => 'WebP',
        };
    }
}
