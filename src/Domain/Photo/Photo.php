<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Photo;

final class Photo
{
    public function __construct(
        private readonly string              $id,
        private readonly string              $tenantId,
        private readonly ?string             $childId,
        private readonly string              $storageKey,
        private readonly string              $filename,
        private readonly string              $mimeType,
        private readonly ?int                $sizeBytes,
        private readonly ?string             $caption,
        private readonly ?string             $createdBy,
        private readonly \DateTimeImmutable  $createdAt,
    ) {}

    public static function create(
        string  $tenantId,
        ?string $childId,
        string  $storageKey,
        string  $filename,
        string  $mimeType,
        ?int    $sizeBytes,
        ?string $caption,
        ?string $createdBy,
    ): self {
        return new self(
            id:         \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:   $tenantId,
            childId:    $childId,
            storageKey: $storageKey,
            filename:   $filename,
            mimeType:   $mimeType,
            sizeBytes:  $sizeBytes,
            caption:    $caption,
            createdBy:  $createdBy,
            createdAt:  new \DateTimeImmutable(),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:         $data['id'],
            tenantId:   $data['tenant_id'],
            childId:    $data['child_id'] ?? null,
            storageKey: $data['storage_key'],
            filename:   $data['filename'],
            mimeType:   $data['mime_type'],
            sizeBytes:  isset($data['size_bytes']) ? (int) $data['size_bytes'] : null,
            caption:    $data['caption'] ?? null,
            createdBy:  $data['created_by'] ?? null,
            createdAt:  new \DateTimeImmutable($data['created_at']),
        );
    }

    public function getId(): string       { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getChildId(): ?string { return $this->childId; }
    public function getStorageKey(): string { return $this->storageKey; }
    public function getFilename(): string { return $this->filename; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): ?int  { return $this->sizeBytes; }
    public function getCaption(): ?string { return $this->caption; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

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
            'created_at'  => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
