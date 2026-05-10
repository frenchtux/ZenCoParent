<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Storage;

interface FileStorageInterface
{
    public function upload(string $key, string $content, string $mimeType): void;

    public function getPublicUrl(string $key): string;

    public function delete(string $key): void;
}
