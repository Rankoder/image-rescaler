<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Services\InputImages;
use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\ImageFileRules;
use App\Application\Validation\NameRule;
use App\Application\Validation\SourceFile;
use App\Domain\Enums\ImageFormat;
use App\Domain\Interfaces\ImageReencoder;
use App\Domain\ValueObjects\ImageInfo;
use PHPUnit\Framework\TestCase;
use Tests\Support\AssertsValidationFailure;
use Tests\Support\FakeImageInspector;
use Tests\Support\InMemoryImageLibrary;

final class InputImagesTest extends TestCase
{
    use AssertsValidationFailure;

    private InMemoryImageLibrary $library;

    protected function setUp(): void
    {
        $this->library = new InMemoryImageLibrary();
    }

    public function testAddsUnderTheChosenNameWithTheExtensionOfTheType(): void
    {
        $name = $this->images(new ImageInfo('image/webp', 300, 200))->add(SourceFile::at('/tmp/upload', 'photo.webp'), 'holiday');

        $this->assertSame('holiday.webp', $name);
        $this->assertSame(['holiday.webp' => '/tmp/upload.reencoded.webp'], $this->library->images);
    }

    public function testWithoutANameUsesASafeVersionOfTheFilesOwn(): void
    {
        $name = $this->images(new ImageInfo('image/jpeg', 300, 200))->add(SourceFile::at('/tmp/upload', '../my photo (1).jpeg'), null);

        $this->assertSame('my_photo__1_.jpg', $name);
    }

    public function testATakenNameIsReportedForTheNameField(): void
    {
        $this->library->images['holiday.png'] = '/elsewhere';

        $this->assertFails(
            [[Field::Name, ErrorCode::AlreadyExists]],
            fn(InputImages $images) => $images->add(SourceFile::at('/tmp/upload', 'photo.png'), 'holiday'),
            new ImageInfo('image/png', 300, 200),
        );
        $this->assertSame(['holiday.png' => '/elsewhere'], $this->library->images);
    }

    public function testFileAndNameErrorsComeTogether(): void
    {
        $this->assertFails(
            [[Field::File, ErrorCode::UnsupportedType], [Field::Name, ErrorCode::InvalidCharacters]],
            fn(InputImages $images) => $images->add(SourceFile::at('/tmp/upload', 'notes.txt'), '../x'),
            null,
        );
        $this->assertSame([], $this->library->images);
    }

    public function testThereIsNoFrameToFill(): void
    {
        $name = $this->images(new ImageInfo('image/png', 40, 40))->add(SourceFile::at('/tmp/upload', 'tiny.png'), null);

        $this->assertSame('tiny.png', $name);
    }

    public function testRejectsAdditionWhenTheLimitOfTenImagesIsReached(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->library->images["img_{$i}.jpg"] = '/existing';
        }

        $this->assertFails(
            [[null, ErrorCode::LimitReached]],
            fn(InputImages $images) => $images->add(SourceFile::at('/tmp/upload', 'extra.jpg'), 'extra'),
            new ImageInfo('image/jpeg', 100, 100),
        );
        $this->assertCount(10, $this->library->images);
    }

    public function testLimitAndFieldErrorsComeTogether(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->library->images["img_{$i}.jpg"] = '/existing';
        }

        $this->assertFails(
            [[null, ErrorCode::LimitReached], [Field::Name, ErrorCode::InvalidCharacters]],
            fn(InputImages $images) => $images->add(SourceFile::at('/tmp/upload', 'extra.jpg'), '../bad'),
            new ImageInfo('image/jpeg', 100, 100),
        );
    }

    /**
     * @param list<array{?Field, ErrorCode}> $expected
     * @param callable(InputImages): mixed $action
     */
    private function assertFails(array $expected, callable $action, ?ImageInfo $image): void
    {
        $this->assertValidationFails($expected, fn() => $action($this->images($image)));
    }

    private function images(?ImageInfo $image): InputImages
    {
        $reencoder = new readonly class implements ImageReencoder {
            public function reencode(string $sourcePath, ImageFormat $format): string
            {
                return $sourcePath . '.reencoded.' . $format->extension();
            }
        };

        return new InputImages($this->library, new ImageFileRules(new FakeImageInspector(image: $image)), new NameRule(), $reencoder);
    }
}
