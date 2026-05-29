<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\MedicalRecord;

final class MedicalAttachment
{
    // Max 10 MB
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private readonly string             $id,
        private readonly string             $tenantId,
        private readonly string             $recordId,
        private readonly string             $filename,
        private readonly string             $mimeType,
        private readonly int                $sizeBytes,
        private readonly string             $storageKey,
        private readonly ?string            $uploadedBy,
        private readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string  $tenantId,
        string  $recordId,
        string  $filename,
        string  $mimeType,
        int     $sizeBytes,
        string  $storageKey,
        ?string $uploadedBy,
    ): self {
        return new self(
            id:         \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:   $tenantId,
            recordId:   $recordId,
            filename:   $filename,
            mimeType:   $mimeType,
            sizeBytes:  $sizeBytes,
            storageKey: $storageKey,
            uploadedBy: $uploadedBy,
            createdAt:  new \DateTimeImmutable(),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:         $data['id'],
            tenantId:   $data['tenant_id'],
            recordId:   $data['record_id'],
            filename:   $data['filename'],
            mimeType:   $data['mime_type'],
            sizeBytes:  (int) $data['size_bytes'],
            storageKey: $data['storage_key'],
            uploadedBy: $data['uploaded_by'] ?? null,
            createdAt:  new \DateTimeImmutable($data['created_at']),
        );
    }

    public function getId(): string                      { return $this->id; }
    public function getTenantId(): string                { return $this->tenantId; }
    public function getRecordId(): string                { return $this->recordId; }
    public function getFilename(): string                { return $this->filename; }
    public function getMimeType(): string                { return $this->mimeType; }
    public function getSizeBytes(): int                  { return $this->sizeBytes; }
    public function getStorageKey(): string              { return $this->storageKey; }
    public function getUploadedBy(): ?string             { return $this->uploadedBy; }
    public function getCreatedAt(): \DateTimeImmutable   { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenantId,
            'record_id'   => $this->recordId,
            'filename'    => $this->filename,
            'mime_type'   => $this->mimeType,
            'size_bytes'  => $this->sizeBytes,
            'storage_key' => $this->storageKey,
            'uploaded_by' => $this->uploadedBy,
            'created_at'  => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
