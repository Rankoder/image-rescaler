<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Services\InputImages;
use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\ImageFileRules;
use App\Application\Validation\NameRule;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationError;
use App\Application\Validation\ValidationFailed;
use App\Domain\Enums\ImageAddOutcome;
use App\Domain\Enums\ImageFormat;
use App\Domain\Interfaces\ImageInspector;
use App\Domain\Interfaces\ImageLibrary;
use App\Domain\Interfaces\ImageReencoder;
use App\Domain\ValueObjects\ImageInfo;
use PHPUnit\Framework\TestCase;

final class InputImagesTest extends TestCase
{
    /** @var array<string, string> name => source path */
    private array $stored = [];

    public function testAddsUnderTheChosenNameWithTheExtensionOfTheType(): void
    {
        $name = $this->images(new ImageInfo('image/webp', 300, 200))->add(SourceFile::at('/tmp/upload', 'photo.webp'), 'holiday');

        $this->assertSame('holiday.webp', $name);
        $this->assertSame(['holiday.webp' => '/tmp/upload.reencoded.webp'], $this->stored);
    }

    public function testWithoutANameUsesASafeVersionOfTheFilesOwn(): void
    {
        $name = $this->images(new ImageInfo('image/jpeg', 300, 200))->add(SourceFile::at('/tmp/upload', '../my photo (1).jpeg'), null);

        $this->assertSame('my_photo__1_.jpg', $name);
    }

    public function testATakenNameIsReportedForTheNameField(): void
    {
        $this->stored['holiday.png'] = '/elsewhere';

        $this->assertFails(
            [[Field::Name, ErrorCode::AlreadyExists]],
            fn(InputImages $images) => $images->add(SourceFile::at('/tmp/upload', 'photo.png'), 'holiday'),
            new ImageInfo('image/png', 300, 200),
        );
        $this->assertSame(['holiday.png' => '/elsewhere'], $this->stored);
    }

    public function testFileAndNameErrorsComeTogether(): void
    {
        $this->assertFails(
            [[Field::File, ErrorCode::UnsupportedType], [Field::Name, ErrorCode::InvalidCharacters]],
            fn(InputImages $images) => $images->add(SourceFile::at('/tmp/upload', 'notes.txt'), '../x'),
            null,
        );
        $this->assertSame([], $this->stored);
    }

    public function testThereIsNoFrameToFill(): void
    {
        $name = $this->images(new ImageInfo('image/png', 40, 40))->add(SourceFile::at('/tmp/upload', 'tiny.png'), null);

        $this->assertSame('tiny.png', $name);
    }

    public function testRejectsAdditionWhenTheLimitOfTenImagesIsReached(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->stored["img_{$i}.jpg"] = '/existing';
        }

        $this->assertFails(
            [[null, ErrorCode::LimitReached]],
            fn(InputImages $images) => $images->add(SourceFile::at('/tmp/upload', 'extra.jpg'), 'extra'),
            new ImageInfo('image/jpeg', 100, 100),
        );
        $this->assertCount(10, $this->stored);
    }

    public function testLimitAndFieldErrorsComeTogether(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->stored["img_{$i}.jpg"] = '/existing';
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
        try {
            $action($this->images($image));
            $this->fail('No validation error.');
        } catch (ValidationFailed $failure) {
            $this->assertSame(
                $expected,
                array_map(static fn(ValidationError $error): array => [$error->field, $error->code], $failure->errors),
            );
        }
    }

    private function images(?ImageInfo $image): InputImages
    {
        $inspector = new readonly class ($image) implements ImageInspector {
            public function __construct(private ?ImageInfo $image) {}

            public function sizeInBytes(string $path): int
            {
                return 1000;
            }

            public function inspect(string $path): ?ImageInfo
            {
                return $this->image;
            }
        };

        $library = new class ($this->stored) implements ImageLibrary {
            /** @param array<string, string> $stored */
            public function __construct(private array &$stored) {}

            public function names(): array
            {
                return array_keys($this->stored);
            }

            public function path(string $name): ?string
            {
                return $this->stored[$name] ?? null;
            }

            public function addWithinLimit(string $sourcePath, string $name, int $limit): ImageAddOutcome
            {
                if (count($this->stored) >= $limit) {
                    return ImageAddOutcome::LimitReached;
                }
                if (isset($this->stored[$name])) {
                    return ImageAddOutcome::AlreadyExists;
                }
                $this->stored[$name] = $sourcePath;

                return ImageAddOutcome::Added;
            }
        };

        $reencoder = new readonly class implements ImageReencoder {
            public function reencode(string $sourcePath, ImageFormat $format): string
            {
                return $sourcePath . '.reencoded.' . $format->extension();
            }
        };

        return new InputImages($library, new ImageFileRules($inspector), new NameRule(), $reencoder);
    }
}
