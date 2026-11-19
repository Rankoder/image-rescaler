<?php

declare(strict_types=1);

// One upload in its own process, for the test of parallel uploads: waits until the given moment, so all start together.

use App\Domain\ValueObjects\ImageValueObject;
use App\Infrastructure\Filesystem\ExclusiveCopy;
use App\Infrastructure\Storage\LocalStorageService;

require __DIR__ . '/../../vendor/autoload.php';

/** @var array{string, string, string, string, string} $arguments */
$arguments = $_SERVER['argv'];
[, $directory, $source, $name, $startAt] = $arguments;

$temporary = $source . '.' . getmypid();
copy($source, $temporary);

time_sleep_until((float) $startAt);
echo (new LocalStorageService($directory, 'http://localhost', new ExclusiveCopy()))->upload(new ImageValueObject($temporary, $name));
