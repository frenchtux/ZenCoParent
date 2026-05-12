<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Photo\Photo;
use ZenCoParent\Domain\Photo\PhotoRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLPhotoRepository extends AbstractRepository implements PhotoRepositoryInterface
{
    public function findById(string $id): ?Photo
    {
        $stmt = $this->pdo->prepare('SELECT * FROM photos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Photo::fromArray($row) : null;
    }

    public function findByTenantId(string $tenantId, ?string $childId = null): array
    {
        $params = ['tenant_id' => $tenantId];
        $sql    = 'SELECT * FROM photos WHERE tenant_id = :tenant_id';

        if ($childId !== null) {
            $sql .= ' AND child_id = :child_id';
            $params['child_id'] = $childId;
        }

        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn(array $row): Photo => Photo::fromArray($row), $stmt->fetchAll());
    }

    public function save(Photo $photo): void
    {
        $now  = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO photos (id, tenant_id, child_id, storage_key, filename, mime_type, size_bytes, caption, created_by, created_at)
             VALUES (:id, :tenant_id, :child_id, :storage_key, :filename, :mime_type, :size_bytes, :caption, :created_by, :created_at)'
        );
        $stmt->execute([
            'id'          => $photo->getId(),
            'tenant_id'   => $photo->getTenantId(),
            'child_id'    => $photo->getChildId(),
            'storage_key' => $photo->getStorageKey(),
            'filename'    => $photo->getFilename(),
            'mime_type'   => $photo->getMimeType(),
            'size_bytes'  => $photo->getSizeBytes(),
            'caption'     => $photo->getCaption(),
            'created_by'  => $photo->getCreatedBy(),
            'created_at'  => $now,
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM photos WHERE id = :id')->execute(['id' => $id]);
    }

    public function existsForTenant(string $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM photos WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
