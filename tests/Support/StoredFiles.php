<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Inspects and cleans up files written to local storage during a test.
 */
final class StoredFiles
{
    private const DIRECTORY = __DIR__ . '/../../storage/';

    public static function path(string $name): string
    {
        return self::DIRECTORY . $name;
    }

    /**
     * @return list<string>
     */
    public static function matching(string $prefix): array
    {
        return glob(self::DIRECTORY . $prefix . '*') ?: [];
    }

    public static function remove(string $prefix): void
    {
        foreach (self::matching($prefix) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
