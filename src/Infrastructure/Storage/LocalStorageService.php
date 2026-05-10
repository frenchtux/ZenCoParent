<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Storage;

use ZenCoParent\Domain\Storage\FileStorageInterface;

final class LocalStorageService implements FileStorageInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $baseUrl,
    ) {}

    public function upload(string $key, string $content, string $mimeType): void
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $dir  = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create storage directory: {$dir}");
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("Failed to write file to local storage: {$key}");
        }
    }

    public function getPublicUrl(string $key): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($key, '/');
    }

    public function delete(string $key): void
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
