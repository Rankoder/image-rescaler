<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Interfaces\StorageServiceInterface;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Models\FileMetadata;
use Dotenv\Dotenv;
use Exception;

class DropboxStorageService extends AbstractStorageService implements StorageServiceInterface
{
    private Dropbox $dropbox;
    private string $accessToken;

    public function __construct()
    {
        parent::__construct();
        $this->loadEnvironmentVariables();
        $this->initializeDropboxClient();
    }

    private function loadEnvironmentVariables(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();

        $this->accessToken = getenv('DROPBOX_ACCESS_TOKEN');

        if (!$this->accessToken) {
            throw new Exception('Dropbox access token is not set.');
        }
    }

    private function initializeDropboxClient(): void
    {
        $app = new DropboxApp(null, null, $this->accessToken);
        $this->dropbox = new Dropbox($app);
    }

    protected function store(string $sourcePath, string $destinationFilename): void
    {
        $dropboxFile = new \Kunnu\Dropbox\DropboxFile($sourcePath);
        try {
            $this->dropbox->upload($dropboxFile, '/Aplikacje/image-rescaler/' . $destinationFilename, ['autorename' => true]);
        } catch (DropboxClientException $e) {
            throw new Exception('File upload to Dropbox failed: ' . $e->getMessage());
        }
    }

    protected function fileExists(string $filename): bool
    {
        try {
            $metadata = $this->dropbox->getMetadata('/Aplikacje/image-rescaler/' . $filename);
            return $metadata instanceof FileMetadata;
        } catch (DropboxClientException $e) {
            return false;
        }
    }

    public function getPublicUrl(string $filePath): string
    {
        return 'https://www.dropbox.com/home/' . $filePath; // ToDo Its need configuration to get proper link...
    }
}
