<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Services\ImageProcessingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageProcessingServiceGenerateUniqueFilenameTest extends TestCase
{
    private ImageProcessingService $service;

    protected function setUp(): void
    {
        $this->service = new ImageProcessingService();
    }

    #[DataProvider('filenameProvider')]
    public function testGenerateUniqueFilename(string $filename, string $outputFilename, array $expected): void
    {
        $result = $this->service->generateUniqueFilename($filename, $outputFilename);
        if (isset($expected['pattern'])) {
            $this->assertMatchesRegularExpression($expected['pattern'], $result);
        } else {
            $this->assertSame($expected['value'], $result);
        }
    }

    public static function filenameProvider(): array
    {
        return [
            'withoutOutputFilename' => [
                'example.jpg',
                '',
                ['pattern' => '/^resized_example_[a-z0-9]{13}\.jpg$/'],
            ],
            'withOutputFilename' => [
                'example.jpg',
                'custom_name',
                ['value' => 'custom_name.jpg'],
            ],
            'withoutExtension' => [
                'example',
                '',
                ['pattern' => '/^resized_example_[a-z0-9]{13}$/'],
            ],
            'withOutputFilenameWithoutExtension' => [
                'example',
                'custom_name',
                ['value' => 'custom_name'],
            ],
        ];
    }
}
