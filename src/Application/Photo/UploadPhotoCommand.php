<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Photo;

final readonly class UploadPhotoCommand
{
    public function __construct(
        public string  $tenantId,
        public ?string $childId,
        public string  $filename,
        public string  $mimeType,
        public int     $sizeBytes,
        public string  $content,
        public ?string $caption,
        public string  $uploadedBy,
    ) {}
}
