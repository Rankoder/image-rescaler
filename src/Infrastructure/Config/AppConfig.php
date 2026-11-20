<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

/**
 * All settings of the application, read once at startup.
 */
final readonly class AppConfig
{
    public function __construct(
        public string $publicBaseUrl,
        public string $storageDirectory,
        public string $inputDirectory,
        public string $temporaryDirectory,
        public int $postMaxSize,
        public AwsConfig $aws,
        public string $dropboxAccessToken,
        public string $dropboxDirectory,
    ) {}

    /**
     * @param array<string, string> $env as returned by getenv()
     */
    public static function fromEnvironment(array $env, string $projectRoot): self
    {
        $value = static fn(string $name, string $default = ''): string => ($env[$name] ?? '') !== '' ? $env[$name] : $default;

        return new self(
            sprintf('http://%s:%s', $value('APP_HOST', 'localhost'), $value('APP_PORT', '8080')),
            $projectRoot . '/storage',
            $value('INPUT_DIR', $projectRoot . '/storage/input'),
            sys_get_temp_dir(),
            ini_parse_quantity((string) ini_get('post_max_size')),
            new AwsConfig(
                $value('AWS_ACCESS_KEY_ID'),
                $value('AWS_SECRET_ACCESS_KEY'),
                $value('AWS_REGION'),
                $value('AWS_BUCKET_NAME'),
            ),
            $value('DROPBOX_ACCESS_TOKEN'),
            rtrim($value('DROPBOX_DIRECTORY', '/Aplikacje/image-rescaler'), '/') ?: '/',
        );
    }
}
