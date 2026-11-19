<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
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
                throw new RuntimeException(sprintf(
                    '%s is stored on Dropbox, but no shared link could be created; the token needs the sharing.write scope. Dropbox said: %s',
                    $path,
                    $e->getMessage(),
                ), 0, $e);
            }

            $links = $this->dropbox->postToAPI('/sharing/list_shared_links', ['path' => $path, 'direct_only' => true])->getDecodedBody();
            $link = is_array($links['links'] ?? null) ? ($links['links'][0] ?? null) : null;
        }

        if (!is_array($link) || !is_string($link['url'] ?? null)) {
            throw new RuntimeException('Dropbox returned no link for ' . $path);
        }

        return $link['url'];
    }

    protected function createIfAbsent(string $sourcePath, string $destinationFilename): bool
    {
        try {
            // In "add" mode without autorename Dropbox refuses a taken path with a conflict instead of renaming
            $this->dropbox->upload(new DropboxFile($sourcePath), $this->path($destinationFilename), ['mode' => 'add', 'autorename' => false]);

            return true;
        } catch (DropboxClientException $e) {
            if (str_contains($e->getMessage(), 'conflict')) {
                return false;
            }

            throw new RuntimeException('File upload to Dropbox failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function path(string $filename): string
    {
        return rtrim($this->directory, '/') . '/' . $filename;
    }
}
