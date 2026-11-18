<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class ParallelUploadsTest extends TestCase
{
    private const int UPLOADS = 16;

    private TestFiles $files;
    private TestFiles $storage;

    protected function setUp(): void
    {
        $this->files = new TestFiles();
        $this->storage = new TestFiles();
    }

    protected function tearDown(): void
    {
        $this->files->remove();
        $this->storage->remove();
    }

    public function testUploadsUnderTheSameNameAtTheSameMomentNeverOverwriteEachOther(): void
    {
        $source = $this->files->jpeg(60, 40, 'thumb.jpg');
        $startAt = (string) (microtime(true) + 1.5);

        $processes = [];
        for ($i = 0; $i < self::UPLOADS; $i++) {
            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/../Support/store-once.php', $this->storage->directory(), $source, 'thumb.jpg', $startAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            $this->assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        $names = [];
        foreach ($processes as [$process, $pipes]) {
            $names[] = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors);
        }

        $stored = array_values(array_diff(scandir($this->storage->directory()) ?: [], ['.', '..']));
        $this->assertCount(self::UPLOADS, array_unique($names), 'Two uploads were given the same name.');
        $this->assertCount(self::UPLOADS, $stored, 'An upload overwrote another one.');
    }
}
