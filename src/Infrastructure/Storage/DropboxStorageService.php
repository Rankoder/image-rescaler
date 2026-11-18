<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Exception;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Models\FileMetadata;

final class DropboxStorageService extends AbstractStorageService
{
    private const string DIRECTORY = '/Aplikacje/image-rescaler/';

    public function __construct(private readonly Dropbox $dropbox) {}

    public function getPublicUrl(string $filePath): string
    {
        return 'https://www.dropbox.com/home/' . $filePath;
    }

    protected function store(string $sourcePath, string $destinationFilename): void
    {
        try {
            $this->dropbox->upload(new DropboxFile($sourcePath), self::DIRECTORY . $destinationFilename, ['autorename' => true]);
        } catch (DropboxClientException $e) {
            throw new Exception('File upload to Dropbox failed: ' . $e->getMessage());
        }
    }

    protected function fileExists(string $filename): bool
    {
        try {
            return $this->dropbox->getMetadata(self::DIRECTORY . $filename) instanceof FileMetadata;
        } catch (DropboxClientException) {
            return false;
        }
    }
}
