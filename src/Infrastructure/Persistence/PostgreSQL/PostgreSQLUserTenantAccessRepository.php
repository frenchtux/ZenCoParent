<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\User\UserTenantAccessRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLUserTenantAccessRepository extends AbstractRepository implements UserTenantAccessRepositoryInterface
{
    public function findTenantsByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.name, t.slug, uta.role, uta.is_active
             FROM user_tenant_access uta
             JOIN tenants t ON t.id = uta.tenant_id
             WHERE uta.user_id = :user_id AND uta.is_active = true
             ORDER BY t.name'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findUsersByTenantId(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email, u.first_name, u.last_name, uta.role, uta.is_active
             FROM user_tenant_access uta
             JOIN users u ON u.id = uta.user_id
             WHERE uta.tenant_id = :tenant_id
             ORDER BY u.email'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function hasAccess(string $userId, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_tenant_access WHERE user_id = :uid AND tenant_id = :tid'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function grant(string $userId, string $tenantId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_tenant_access (user_id, tenant_id, role, is_active)
             VALUES (:uid, :tid, :role, true)
             ON CONFLICT (user_id, tenant_id) DO UPDATE SET role = EXCLUDED.role, is_active = true'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId, 'role' => $role]);
    }

    public function revoke(string $userId, string $tenantId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_tenant_access WHERE user_id = :uid AND tenant_id = :tid'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId]);
    }

    public function setTenants(string $userId, array $tenantIds, string $role): void
    {
        $this->pdo->beginTransaction();
        try {
            // Remove accesses not in the new list
            $this->pdo->prepare('DELETE FROM user_tenant_access WHERE user_id = :uid')
                ->execute(['uid' => $userId]);
            // Insert new list
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_tenant_access (user_id, tenant_id, role, is_active)
                 VALUES (:uid, :tid, :role, true)'
            );
            foreach ($tenantIds as $tenantId) {
                $stmt->execute(['uid' => $userId, 'tid' => $tenantId, 'role' => $role]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
