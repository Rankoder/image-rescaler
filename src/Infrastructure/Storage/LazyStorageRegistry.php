<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\StorageRegistry;
use App\Domain\Interfaces\StorageServiceInterface;
use Closure;
use LogicException;

/**
 * Builds a storage only when it is asked for, so a missing S3 or Dropbox setting fails only the uploads that use it.
 */
final readonly class LazyStorageRegistry implements StorageRegistry
{
    /**
     * @param array<value-of<StorageType>, Closure(): StorageServiceInterface> $factories
     */
    public function __construct(private array $factories) {}

    public function get(StorageType $type): StorageServiceInterface
    {
        $factory = $this->factories[$type->value] ?? throw new LogicException("No storage is registered for {$type->value}.");

        return $factory();
    }
}
