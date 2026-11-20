<?php

declare(strict_types=1);

namespace Tests\Functional\Infrastructure\Filesystem;

use App\Infrastructure\Filesystem\ExclusiveCopy;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class ExclusiveCopyTest extends TestCase
{
    private TestFiles $sources;
    private TestFiles $target;

    protected function setUp(): void
    {
        $this->sources = new TestFiles();
        $this->target = new TestFiles();
    }

    protected function tearDown(): void
    {
        $this->sources->remove();
        $this->target->remove();
    }

    public function testRemovesStalePartialFilesAndKeepsRecentAndUnrelatedHiddenFiles(): void
    {
        $stale = $this->target->withContent('stale', '.0123456789abcdef-old_v1.jpg');
        touch($stale, time() - 120);

        $recent = $this->target->withContent('active', '.fedcba9876543210-active_v1.jpg');
        $lock = $this->target->withContent('', '.lock');
        $otherHidden = $this->target->withContent('keep', '.gitkeep');
        $shortPrefix = $this->target->withContent('keep', '.1234-photo.jpg');
        $symlinkTarget = $this->sources->withContent('outside', 'outside.jpg');
        $symlinkPartial = $this->target->directory() . '/.aaaaaaaaaaaaaaaa-link.jpg';
        symlink($symlinkTarget, $symlinkPartial);
        touch($otherHidden, time() - 120);
        touch($shortPrefix, time() - 120);

        $source = $this->sources->withContent('image', 'new.jpg');
        $copy = new ExclusiveCopy(staleAfterSeconds: 60);

        $this->assertTrue($copy->create($source, $this->target->directory(), 'new_v1.jpg'));

        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($recent);
        $this->assertFileExists($lock);
        $this->assertFileExists($otherHidden);
        $this->assertFileExists($shortPrefix);
        $this->assertTrue(is_link($symlinkPartial));
        unlink($symlinkPartial);
        $this->assertStringEqualsFile($this->target->directory() . '/new_v1.jpg', 'image');
    }
}
