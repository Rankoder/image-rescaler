<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Models\FileMetadata;
use RuntimeException;

final class DropboxStorageService extends AbstractStorageService
{
    public function __construct(
        private readonly Dropbox $dropbox,
        private readonly string $directory,
    ) {}

    public function getPublicUrl(string $filePath): string
    {
        $path = $this->path($filePath);

        try {
            $link = $this->dropbox->postToAPI('/sharing/create_shared_link_with_settings', ['path' => $path])->getDecodedBody();
        } catch (DropboxClientException $e) {
            if (!str_contains($e->getMessage(), 'shared_link_already_exists')) {
                throw new RuntimeException('Creating a Dropbox link failed: ' . $e->getMessage(), 0, $e);
            }

            $links = $this->dropbox->postToAPI('/sharing/list_shared_links', ['path' => $path, 'direct_only' => true])->getDecodedBody();
            $link = is_array($links['links'] ?? null) ? ($links['links'][0] ?? null) : null;
        }

        if (!is_array($link) || !is_string($link['url'] ?? null)) {
            throw new RuntimeException('Dropbox returned no link for ' . $path);
        }

        return $link['url'];
    }

    protected function store(string $sourcePath, string $destinationFilename): void
    {
        try {
            $this->dropbox->upload(new DropboxFile($sourcePath), $this->path($destinationFilename), ['autorename' => true]);
        } catch (DropboxClientException $e) {
            throw new RuntimeException('File upload to Dropbox failed: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function fileExists(string $filename): bool
    {
        try {
            return $this->dropbox->getMetadata($this->path($filename)) instanceof FileMetadata;
        } catch (DropboxClientException $e) {
            // Only "not found" means the name is free; an auth or network error must not pass for it
            if (str_contains($e->getMessage(), 'not_found')) {
                return false;
            }

            throw new RuntimeException('Checking a file on Dropbox failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function path(string $filename): string
    {
        return $this->directory . '/' . $filename;
    }
}
