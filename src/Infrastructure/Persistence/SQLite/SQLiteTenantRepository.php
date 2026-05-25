<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Tenant\Tenant;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class SQLiteTenantRepository extends AbstractRepository implements TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Tenant::fromArray($row) : null;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row !== false ? Tenant::fromArray($row) : null;
    }

    public function save(Tenant $tenant): void
    {
        $data = $tenant->toArray();
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenants (id, name, slug, is_active, created_at, updated_at)
             VALUES (:id, :name, :slug, :is_active, datetime(\'now\'), datetime(\'now\'))'
        );
        $stmt->execute([
            'id'        => $data['id'],
            'name'      => $data['name'],
            'slug'      => $data['slug'],
            'is_active' => $data['is_active'] ? 1 : 0,
        ]);
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenants ORDER BY created_at DESC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => Tenant::fromArray($r), $stmt->fetchAll());
    }

    public function updateModulesOverride(string $id, ?array $modules): void
    {
        // modules_override column may not exist in community SQLite schema — no-op
    }

    public function setActive(string $id, bool $active): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenants SET is_active = :active, updated_at = datetime('now') WHERE id = :id"
        );
        $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0]);
    }
}
