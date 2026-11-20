<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Services\OutputFilename;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Services\StoredImage;
use App\Application\Validation\ImageFileRules;
use App\Application\Validation\NameRule;
use App\Application\Validation\ResizeInput;
use App\Application\Validation\ResizeRequestValidator;
use App\Application\Validation\SourceFile;
use App\Domain\Enums\ImageAddOutcome;
use App\Domain\Enums\StorageType;
use App\Domain\Interfaces\ImageInspector;
use App\Domain\Interfaces\ImageLibrary;
use App\Domain\Interfaces\ImageResizer;
use App\Domain\Interfaces\StorageRegistry;
use App\Domain\Interfaces\StorageServiceInterface;
use App\Domain\ValueObjects\ImageInfo;
use App\Domain\ValueObjects\ImageValueObject;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResizeAndStoreImageTest extends TestCase
{
    /** @var list<array{string, int, int, string}> */
    private array $resized = [];

    public function testDoesNotResizeWhenSelectingTheStorageFails(): void
    {
        $storages = new class implements StorageRegistry {
            public function get(StorageType $type): StorageServiceInterface
            {
                throw new RuntimeException('Dropbox access token is not set.');
            }
        };

        $service = $this->service($storages);

        try {
            $service->handle(new ResizeInput(SourceFile::at('/tmp/upload', 'photo.jpg'), '60', '40', 'dropbox', 'thumb'));
            $this->fail('Expected storage selection to fail.');
        } catch (RuntimeException $failure) {
            $this->assertSame('Dropbox access token is not set.', $failure->getMessage());
            $this->assertSame([], $this->resized);
        }
    }

    /**
     * @return iterable<string, array{StorageType}>
     */
    public static function storageTypes(): iterable
    {
        foreach (StorageType::cases() as $type) {
            yield $type->value => [$type];
        }
    }

    #[DataProvider('storageTypes')]
    public function testStoresTheResizedImageInTheChosenStorage(StorageType $type): void
    {
        $storage = new class implements StorageServiceInterface {
            /** @var list<ImageValueObject> */
            public array $uploaded = [];

            public function upload(ImageValueObject $image): string
            {
                $this->uploaded[] = $image;

                return 'thumb_v1.jpg';
            }

            public function getPublicUrl(string $filePath): string
            {
                return 'https://storage.test/' . $filePath;
            }
        };
        $storages = new class ($storage) implements StorageRegistry {
            public ?StorageType $requested = null;

            public function __construct(private readonly StorageServiceInterface $storage) {}

            public function get(StorageType $type): StorageServiceInterface
            {
                $this->requested = $type;

                return $this->storage;
            }
        };

        $stored = $this->service($storages)->handle(
            new ResizeInput(SourceFile::at('/tmp/upload', 'photo.jpg'), '60', '40', $type->value, 'thumb'),
        );

        $this->assertSame($type, $storages->requested);
        $this->assertSame([['/tmp/upload', 60, 40, 'thumb.jpg']], $this->resized);
        $this->assertCount(1, $storage->uploaded);
        $this->assertEquals(new StoredImage('thumb_v1.jpg', 'https://storage.test/thumb_v1.jpg'), $stored);
    }

    private function service(StorageRegistry $storages): ResizeAndStoreImage
    {
        $record = function (string $sourcePath, int $width, int $height, string $filename): void {
            $this->resized[] = [$sourcePath, $width, $height, $filename];
        };

        $resizer = new readonly class ($record) implements ImageResizer {
            /** @param Closure(string, int, int, string): void $record */
            public function __construct(private Closure $record) {}

            public function cover(string $sourcePath, int $width, int $height, string $filename): ImageValueObject
            {
                ($this->record)($sourcePath, $width, $height, $filename);

                return new ImageValueObject('/tmp/' . $filename, $filename);
            }
        };
        $inspector = new readonly class implements ImageInspector {
            public function sizeInBytes(string $path): int
            {
                return 1000;
            }

            public function inspect(string $path): ImageInfo
            {
                return new ImageInfo('image/jpeg', 300, 200);
            }
        };

        $library = new readonly class implements ImageLibrary {
            public function names(): array
            {
                return [];
            }

            public function path(string $name): ?string
            {
                return null;
            }

            public function addWithinLimit(string $sourcePath, string $name, int $limit): ImageAddOutcome
            {
                return ImageAddOutcome::Added;
            }
        };

        return new ResizeAndStoreImage(
            new ResizeRequestValidator(new ImageFileRules($inspector), new NameRule(), $library),
            new OutputFilename(),
            $resizer,
            $storages,
        );
    }
}
