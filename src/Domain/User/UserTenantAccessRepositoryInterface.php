<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\User;

interface UserTenantAccessRepositoryInterface
{
    /** Return all tenant IDs accessible by a user. */
    public function findTenantsByUserId(string $userId): array;

    /** Return all user IDs that have access to a tenant. */
    public function findUsersByTenantId(string $tenantId): array;

    /** Check whether the user already has access to the tenant. */
    public function hasAccess(string $userId, string $tenantId): bool;

    /** Grant a user access to a tenant with the given role. */
    public function grant(string $userId, string $tenantId, string $role): void;

    /** Revoke a user's access to a tenant. */
    public function revoke(string $userId, string $tenantId): void;

    /** Replace the full list of tenant accesses for a user (used by admin bulk assign). */
    public function setTenants(string $userId, array $tenantIds, string $role): void;
}
