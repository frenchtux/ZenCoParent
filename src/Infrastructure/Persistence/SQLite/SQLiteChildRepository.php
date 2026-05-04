<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Child\Child;
use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class SQLiteChildRepository extends AbstractRepository implements ChildRepositoryInterface
{
    public function findById(string $id): ?Child
    {
        $stmt = $this->pdo->prepare('SELECT * FROM children WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Child::fromArray($row) : null;
    }

    public function findByTenantId(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM children WHERE tenant_id = :tenant_id ORDER BY first_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();
        return array_map(static fn(array $row): Child => Child::fromArray($row), $rows);
    }

    public function save(Child $child): void
    {
        $data = $child->toArray();
        $stmt = $this->pdo->prepare(
            'INSERT INTO children (
                id, tenant_id, first_name, last_name, birthdate,
                medical_info, school_info, created_by, created_at, updated_at
            ) VALUES (
                :id, :tenant_id, :first_name, :last_name, :birthdate,
                :medical_info, :school_info, :created_by, datetime(\'now\'), datetime(\'now\')
            )'
        );
        $stmt->execute([
            'id'           => $data['id'],
            'tenant_id'    => $data['tenant_id'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'birthdate'    => $data['birthdate'],
            'medical_info' => json_encode($data['medical_info'], JSON_THROW_ON_ERROR),
            'school_info'  => json_encode($data['school_info'], JSON_THROW_ON_ERROR),
            'created_by'   => $data['created_by'],
        ]);
    }

    public function update(Child $child): void
    {
        $data = $child->toArray();
        $stmt = $this->pdo->prepare(
            'UPDATE children SET
                first_name   = :first_name,
                last_name    = :last_name,
                birthdate    = :birthdate,
                medical_info = :medical_info,
                school_info  = :school_info,
                updated_at   = :updated_at
            WHERE id = :id'
        );
        $stmt->execute([
            'id'           => $data['id'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'birthdate'    => $data['birthdate'],
            'medical_info' => json_encode($data['medical_info'], JSON_THROW_ON_ERROR),
            'school_info'  => json_encode($data['school_info'], JSON_THROW_ON_ERROR),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM children WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
