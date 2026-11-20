<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Config\AwsConfig;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testReadsEverySettingFromTheEnvironment(): void
    {
        $config = AppConfig::fromEnvironment([
            'APP_HOST' => 'images.test',
            'APP_PORT' => '8081',
            'INPUT_DIR' => '/data/input',
            'AWS_ACCESS_KEY_ID' => 'key',
            'AWS_SECRET_ACCESS_KEY' => 'secret',
            'AWS_REGION' => 'eu-central-1',
            'AWS_BUCKET_NAME' => 'bucket',
            'DROPBOX_ACCESS_TOKEN' => 'token',
            'DROPBOX_DIRECTORY' => '/Apps/thumbnails/',
        ], '/app');

        $this->assertSame('http://images.test:8081', $config->publicBaseUrl);
        $this->assertSame('/app/storage', $config->storageDirectory);
        $this->assertSame('/data/input', $config->inputDirectory);
        $this->assertEquals(new AwsConfig('key', 'secret', 'eu-central-1', 'bucket'), $config->aws);
        $this->assertSame('token', $config->dropboxAccessToken);
        $this->assertSame('/Apps/thumbnails', $config->dropboxDirectory);
    }

    public function testFallsBackToDefaultsForMissingOrEmptyValues(): void
    {
        $config = AppConfig::fromEnvironment(['APP_PORT' => ''], '/app');

        $this->assertSame('http://localhost:8080', $config->publicBaseUrl);
        $this->assertSame('/app/storage/input', $config->inputDirectory);
        $this->assertSame('', $config->dropboxAccessToken);
        $this->assertSame('/Aplikacje/image-rescaler', $config->dropboxDirectory);
    }

    public function testKeepsADropboxDirectoryWithoutALeadingSlashForStorageValidation(): void
    {
        $config = AppConfig::fromEnvironment(['DROPBOX_DIRECTORY' => 'Apps/thumbnails/'], '/app');

        $this->assertSame('Apps/thumbnails', $config->dropboxDirectory);
    }

    public function testTheDropboxRootIsAllowed(): void
    {
        $this->assertSame('/', AppConfig::fromEnvironment(['DROPBOX_DIRECTORY' => '/'], '/app')->dropboxDirectory);
    }
}
