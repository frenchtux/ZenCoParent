<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Photo;

final readonly class PhotoDTO
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public ?string $childId,
        public string  $storageKey,
        public string  $filename,
        public string  $mimeType,
        public ?int    $sizeBytes,
        public ?string $caption,
        public ?string $createdBy,
        public string  $createdAt,
        public string  $url,
    ) {}

    public static function fromPhoto(
        \ZenCoParent\Domain\Photo\Photo $photo,
        string $url,
    ): self {
        return new self(
            id:         $photo->getId(),
            tenantId:   $photo->getTenantId(),
            childId:    $photo->getChildId(),
            storageKey: $photo->getStorageKey(),
            filename:   $photo->getFilename(),
            mimeType:   $photo->getMimeType(),
            sizeBytes:  $photo->getSizeBytes(),
            caption:    $photo->getCaption(),
            createdBy:  $photo->getCreatedBy(),
            createdAt:  $photo->getCreatedAt()->format(\DateTimeInterface::ATOM),
            url:        $url,
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenantId,
            'child_id'    => $this->childId,
            'storage_key' => $this->storageKey,
            'filename'    => $this->filename,
            'mime_type'   => $this->mimeType,
            'size_bytes'  => $this->sizeBytes,
            'caption'     => $this->caption,
            'created_by'  => $this->createdBy,
            'created_at'  => $this->createdAt,
            'url'         => $this->url,
        ];
    }
}
