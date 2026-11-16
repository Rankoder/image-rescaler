<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Application\Services\ImageValidationService;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageValidationServiceTest extends TestCase
{
    private ImageValidationService $service;

    protected function setUp(): void
    {
        $this->service = new ImageValidationService();

        // Mock the environment variables
        putenv('ALLOWED_IMAGE_TYPES=image/jpeg,image/png,image/jpg');
        putenv('MAX_FILE_SIZE=5242880'); // 5 MB
        putenv('MAX_IMAGE_WIDTH=5000');
        putenv('MAX_IMAGE_HEIGHT=5000');

        // Ensure test files exist
        $this->createMockFile(__DIR__ . '/test_image.jpg', 1024); // 1 KB
        $this->createMockFile(__DIR__ . '/test_image.gif', 1024); // 1 KB
        $this->createMockFile(sys_get_temp_dir() . '/mock_large_test_image.jpg', 6 * 1024 * 1024); // 6 MB
    }

    private function createMockFile(string $path, int $size): void
    {
        if (!file_exists($path)) {
            file_put_contents($path, str_repeat('A', $size));
        }
    }

    #[DataProvider('fileProvider')]
    public function testValidate(array $file, int $maxWidth, int $maxHeight, ?string $expectedExceptionMessage): void
    {
        if ($expectedExceptionMessage !== null) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $this->service->validate($file, $maxWidth, $maxHeight);

        if ($expectedExceptionMessage === null) {
            $this->assertTrue(true); // No exception was thrown
        }
    }

    public static function fileProvider(): array
    {
        return [
            'valid file' => [
                ['name' => 'image.jpg', 'tmp_name' => __DIR__ . '/test_image.jpg'],
                4000,
                3000,
                null,
            ],
            'invalid file type' => [
                ['name' => 'image.gif', 'tmp_name' => __DIR__ . '/test_image.gif'],
                4000,
                3000,
                "Invalid image file type: gif. Allowed types are: jpeg, png, jpg",
            ],
            'file size exceeded' => [
                ['name' => 'image.jpg', 'tmp_name' => sys_get_temp_dir() . '/mock_large_test_image.jpg'],
                4000,
                3000,
                "File size exceeds the maximum limit of 5 MB.",
            ],
            'image dimensions exceeded' => [
                ['name' => 'image.jpg', 'tmp_name' => __DIR__ . '/test_image.jpg'],
                6000,
                6000,
                "Image dimensions exceed the maximum allowed size of 5000x5000.",
            ],
        ];
    }

    protected function tearDown(): void
    {
        $files = [
            __DIR__ . '/test_image.jpg',
            __DIR__ . '/test_image.gif',
            sys_get_temp_dir() . '/mock_large_test_image.jpg',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
