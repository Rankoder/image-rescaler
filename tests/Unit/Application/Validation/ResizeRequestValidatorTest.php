<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Validation;

use App\Application\Validation\ErrorCode;
use App\Application\Validation\Field;
use App\Application\Validation\ImageFileRules;
use App\Application\Validation\NameRule;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\ResizeRequest;
use App\Application\Validation\ResizeRequestValidator;
use App\Application\Validation\SourceFile;
use App\Application\Validation\ValidationError;
use App\Application\Validation\ValidationFailed;
use App\Domain\Enums\ImageFormat;
use App\Domain\Enums\ImageAddOutcome;
use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\ImageInspector;
use App\Domain\Interfaces\ImageLibrary;
use App\Domain\ValueObjects\ImageInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResizeRequestValidatorTest extends TestCase
{
    public function testAValidInputBecomesARequest(): void
    {
        $request = $this->validator(1000, new ImageInfo('image/png', 300, 200))->validate(
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.png'), '60', '40', 's3', 'thumbnail'),
        );

        $this->assertEquals(
            new ResizeRequest('/tmp/upload', 'photo.png', ImageFormat::Png, 60, 40, StorageType::S3, 'thumbnail'),
            $request,
        );
    }

    public function testServiceAndOutputFilenameAreOptional(): void
    {
        $request = $this->validator(1000, new ImageInfo('image/jpeg', 300, 200))->validate(
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.jpeg'), '60', '40', '', ''),
        );

        $this->assertSame([StorageType::Local, null], [$request->storage, $request->outputFilename]);
    }

    public function testTheLimitsThemselvesAreAccepted(): void
    {
        $request = $this->validator(5 * 1024 * 1024, new ImageInfo('image/webp', 5000, 5000))->validate(
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.webp'), '5000', '40', null, str_repeat('a', 100)),
        );

        $this->assertSame([5000, 40], [$request->width, $request->height]);
    }

    /**
     * @return iterable<string, array{int, ?ImageInfo, string, ErrorCode}>
     */
    public static function rejectedFiles(): iterable
    {
        yield 'empty' => [0, null, 'photo.jpg', ErrorCode::Empty];
        yield 'one byte over 5 MB' => [5 * 1024 * 1024 + 1, new ImageInfo('image/jpeg', 300, 200), 'photo.jpg', ErrorCode::TooLarge];
        yield 'not an image' => [1000, null, 'photo.jpg', ErrorCode::UnsupportedType];
        yield 'GIF' => [1000, new ImageInfo('image/gif', 300, 200), 'photo.gif', ErrorCode::UnsupportedType];
        yield 'extension of another format' => [1000, new ImageInfo('image/png', 300, 200), 'photo.jpg', ErrorCode::ExtensionMismatch];
        yield 'no extension' => [1000, new ImageInfo('image/png', 300, 200), 'photo', ErrorCode::ExtensionMismatch];
        yield 'over 5000 px high' => [1000, new ImageInfo('image/png', 300, 5001), 'photo.png', ErrorCode::DimensionsTooLarge];
        yield 'narrower than the frame' => [1000, new ImageInfo('image/png', 59, 200), 'photo.png', ErrorCode::SmallerThanFrame];
        yield 'lower than the frame' => [1000, new ImageInfo('image/png', 300, 39), 'photo.png', ErrorCode::SmallerThanFrame];
    }

    #[DataProvider('rejectedFiles')]
    public function testRejectsTheFile(int $bytes, ?ImageInfo $image, string $name, ErrorCode $code): void
    {
        $this->assertFails(
            [[Field::File, $code]],
            $this->validator($bytes, $image),
            new ResizeInput(SourceFile::at('/tmp/upload', $name), '60', '40'),
        );
    }

    public function testAProblemFoundByTheEntryPointIsReportedForTheFile(): void
    {
        $this->assertFails(
            [[Field::File, ErrorCode::OutsideInputDirectory]],
            $this->validator(1000, new ImageInfo('image/png', 300, 200)),
            new ResizeInput(SourceFile::rejected(ErrorCode::OutsideInputDirectory), '60', '40'),
        );
    }

    public function testTheSourceIsNotComparedWithAnInvalidFrame(): void
    {
        $this->assertFails(
            [[Field::MaxWidth, ErrorCode::OutOfRange]],
            $this->validator(1000, new ImageInfo('image/png', 50, 50)),
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.png'), '6000', '40'),
        );
    }

    public function testAHugeNumberIsOutOfRangeRatherThanOverflowing(): void
    {
        $this->assertFails(
            [[Field::MaxHeight, ErrorCode::OutOfRange]],
            $this->validator(1000, new ImageInfo('image/png', 300, 200)),
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.png'), '60', '99999999999999999999999'),
        );
    }

    public function testATrailingNewlineIsNotPartOfAValidValue(): void
    {
        $this->assertFails(
            [[Field::MaxWidth, ErrorCode::NotInteger], [Field::OutputFilename, ErrorCode::InvalidCharacters]],
            $this->validator(1000, new ImageInfo('image/png', 300, 200)),
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.png'), "60\n", '40', null, "thumbnail\n"),
        );
    }

    public function testLeadingZerosDoNotChangeTheNumber(): void
    {
        $request = $this->validator(1000, new ImageInfo('image/png', 300, 200))->validate(
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.png'), '000060', '0040'),
        );

        $this->assertSame([60, 40], [$request->width, $request->height]);
    }

    public function testAnImageFromTheInputDirectoryBecomesTheSource(): void
    {
        $request = $this->validator(1000, new ImageInfo('image/png', 300, 200), ['portrait.png' => '/data/input/portrait.png'])
            ->validate(new ResizeInput(null, '60', '40', image: 'portrait.png'));

        $this->assertSame(['/data/input/portrait.png', 'portrait.png'], [$request->sourcePath, $request->sourceName]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function imageNamesNotFound(): iterable
    {
        yield 'missing' => ['missing.png'];
        yield 'parent directory' => ['../portrait.png'];
        yield 'hidden file' => ['.portrait.png'];
        yield 'other extension' => ['notes.txt'];
    }

    #[DataProvider('imageNamesNotFound')]
    public function testAnImageNameThatIsNotAPlainImageInTheDirectoryIsNotFound(string $image): void
    {
        // The library would resolve these names, so only the validator's own rule can reject them
        $library = ['../portrait.png' => '/etc/x', '.portrait.png' => '/x', 'notes.txt' => '/x'];

        $this->assertFails(
            [[Field::Image, ErrorCode::NotFound]],
            $this->validator(1000, new ImageInfo('image/png', 300, 200), $library),
            new ResizeInput(null, '60', '40', image: $image),
        );
    }

    public function testFileAndImageTogetherConflictAlongsideTheOtherErrors(): void
    {
        $this->assertFails(
            [[Field::Image, ErrorCode::ConflictsWithFile], [Field::MaxWidth, ErrorCode::OutOfRange]],
            $this->validator(1000, new ImageInfo('image/png', 300, 200), ['portrait.png' => '/data/input/portrait.png']),
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.png'), '39', '40', image: 'portrait.png'),
        );
    }

    public function testFileRulesAreReportedForTheImageField(): void
    {
        $this->assertFails(
            [[Field::Image, ErrorCode::SmallerThanFrame]],
            $this->validator(1000, new ImageInfo('image/png', 30, 20), ['tiny.png' => '/data/input/tiny.png']),
            new ResizeInput(null, '60', '40', image: 'tiny.png'),
        );
    }

    public function testEveryInvalidFieldIsReportedInTheOrderOfTheFields(): void
    {
        $this->assertFails(
            [
                [Field::File, ErrorCode::Required],
                [Field::MaxWidth, ErrorCode::Required],
                [Field::MaxHeight, ErrorCode::NotInteger],
                [Field::Service, ErrorCode::Unsupported],
                [Field::OutputFilename, ErrorCode::TooLong],
            ],
            $this->validator(1000, null),
            new ResizeInput(null, null, '4.5', 'S3', str_repeat('a', 101)),
        );
    }

    /**
     * @param list<array{Field, ErrorCode}> $expected
     */
    private function assertFails(array $expected, ResizeRequestValidator $validator, ResizeInput $input): void
    {
        try {
            $validator->validate($input);
            $this->fail('The input passed validation.');
        } catch (ValidationFailed $failure) {
            $this->assertSame(
                $expected,
                array_map(static fn(ValidationError $error): array => [$error->field, $error->code], $failure->errors),
            );
        }
    }

    /**
     * @param array<string, string> $library images in the input directory, name => path
     */
    private function validator(int $bytes, ?ImageInfo $image, array $library = []): ResizeRequestValidator
    {
        return new ResizeRequestValidator(new ImageFileRules(new readonly class ($bytes, $image) implements ImageInspector {
            public function __construct(private int $bytes, private ?ImageInfo $image) {}

            public function sizeInBytes(string $path): int
            {
                return $this->bytes;
            }

            public function inspect(string $path): ?ImageInfo
            {
                return $this->image;
            }
        }), new NameRule(), new readonly class ($library) implements ImageLibrary {
            /** @param array<string, string> $images */
            public function __construct(private array $images) {}

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
                return ImageAddOutcome::Added;
            }
        });
    }
}
