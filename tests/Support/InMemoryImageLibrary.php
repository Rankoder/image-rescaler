<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Enums\ImageAddOutcome;
use App\Domain\Interfaces\ImageLibrary;

final class InMemoryImageLibrary implements ImageLibrary
{
    /**
     * @param array<string, string> $images name => source path
     */
    public function __construct(public array $images = []) {}

    public function names(): array
    {
        return array_keys($this->images);
    }

    public function path(string $name): ?string
    {
        return $this->images[$name] ?? null;
    }

    public function addWithinLimit(string $sourcePath, string $name, int $limit): ImageAddOutcome
    {
        if (count($this->images) >= $limit) {
            return ImageAddOutcome::LimitReached;
        }
        if (isset($this->images[$name])) {
            return ImageAddOutcome::AlreadyExists;
        }
        $this->images[$name] = $sourcePath;

        return ImageAddOutcome::Added;
    }
}
