<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\MedicalRecord\MedicalAttachment;
use ZenCoParent\Domain\MedicalRecord\MedicalAttachmentRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLMedicalAttachmentRepository extends AbstractRepository implements MedicalAttachmentRepositoryInterface
{
    public function findByRecordId(string $recordId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM medical_attachments WHERE record_id = :rid ORDER BY created_at ASC'
        );
        $stmt->execute(['rid' => $recordId]);
        return array_map(
            static fn(array $row): MedicalAttachment => MedicalAttachment::fromArray($row),
            $stmt->fetchAll()
        );
    }

    public function findById(string $id): ?MedicalAttachment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM medical_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? MedicalAttachment::fromArray($row) : null;
    }

    public function save(MedicalAttachment $a): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO medical_attachments (id, tenant_id, record_id, filename, mime_type, size_bytes, storage_key, uploaded_by, created_at)
             VALUES (:id, :tenant_id, :record_id, :filename, :mime_type, :size_bytes, :storage_key, :uploaded_by, NOW())'
        );
        $stmt->execute([
            'id'          => $a->getId(),
            'tenant_id'   => $a->getTenantId(),
            'record_id'   => $a->getRecordId(),
            'filename'    => $a->getFilename(),
            'mime_type'   => $a->getMimeType(),
            'size_bytes'  => $a->getSizeBytes(),
            'storage_key' => $a->getStorageKey(),
            'uploaded_by' => $a->getUploadedBy(),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM medical_attachments WHERE id = :id')
            ->execute(['id' => $id]);
    }
}
