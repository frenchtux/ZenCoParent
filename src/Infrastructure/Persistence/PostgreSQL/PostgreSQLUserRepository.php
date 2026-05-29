<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLUserRepository extends AbstractRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? User::fromArray($row) : null;
    }

    public function findByEmail(string $tenantId, string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE tenant_id = :tenant_id AND email = :email'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'email'     => $email,
        ]);
        $row = $stmt->fetch();
        return $row !== false ? User::fromArray($row) : null;
    }

    public function findByTenantId(string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();
        return array_map(static fn(array $row): User => User::fromArray($row), $rows);
    }

    public function save(User $user): void
    {
        $data = $user->toArray();
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (
                id, tenant_id, email, password_hash, first_name, last_name,
                phone, address, role, is_active, email_verified_at, last_login_at,
                created_at, updated_at, must_change_credentials
            ) VALUES (
                :id, :tenant_id, :email, :password_hash, :first_name, :last_name,
                :phone, :address, :role, :is_active, :email_verified_at, :last_login_at,
                :created_at, :updated_at, :must_change_credentials
            )'
        );
        $stmt->execute([
            'id'                      => $data['id'],
            'tenant_id'               => $data['tenant_id'],
            'email'                   => $data['email'],
            'password_hash'           => $data['password_hash'],
            'first_name'              => $data['first_name'],
            'last_name'               => $data['last_name'],
            'phone'                   => $data['phone'],
            'address'                 => $data['address'],
            'role'                    => $data['role'],
            'is_active'               => $data['is_active'] ? 1 : 0,
            'email_verified_at'       => $data['email_verified_at'],
            'last_login_at'           => $data['last_login_at'],
            'created_at'              => $data['created_at'],
            'updated_at'              => $data['updated_at'],
            'must_change_credentials' => $data['must_change_credentials'] ? 1 : 0,
        ]);
    }

    public function update(User $user): void
    {
        $data = $user->toArray();
        $stmt = $this->pdo->prepare(
            'UPDATE users SET
                email                    = :email,
                password_hash            = :password_hash,
                first_name               = :first_name,
                last_name                = :last_name,
                phone                    = :phone,
                address                  = :address,
                role                     = :role,
                is_active                = :is_active,
                email_verified_at        = :email_verified_at,
                last_login_at            = :last_login_at,
                must_change_credentials  = :must_change_credentials,
                updated_at               = NOW()
            WHERE id = :id'
        );
        $stmt->execute([
            'id'                      => $data['id'],
            'email'                   => $data['email'],
            'password_hash'           => $data['password_hash'],
            'first_name'              => $data['first_name'],
            'last_name'               => $data['last_name'],
            'phone'                   => $data['phone'],
            'address'                 => $data['address'],
            'role'                    => $data['role'],
            'is_active'               => $data['is_active'] ? 1 : 0,
            'email_verified_at'       => $data['email_verified_at'],
            'last_login_at'           => $data['last_login_at'],
            'must_change_credentials' => $data['must_change_credentials'] ? 1 : 0,
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function existsByEmail(string $tenantId, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND email = :email'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'email'     => $email,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
