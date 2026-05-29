<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Invitation\Invitation;
use ZenCoParent\Domain\Invitation\InvitationRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLInvitationRepository extends AbstractRepository implements InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO invitations
                (id, tenant_id, invited_by, email, role, token, accepted_at, expires_at, created_at)
             VALUES
                (:id, :tenant_id, :invited_by, :email, :role, :token, :accepted_at, :expires_at, NOW())'
        );
        $stmt->execute([
            'id'          => $invitation->getId(),
            'tenant_id'   => $invitation->getTenantId(),
            'invited_by'  => $invitation->getInvitedBy(),
            'email'       => $invitation->getEmail(),
            'role'        => $invitation->getRole(),
            'token'       => $invitation->getToken(),
            'accepted_at' => null,
            'expires_at'  => $invitation->getExpiresAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByToken(string $token): ?Invitation
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invitations WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        return $row !== false ? Invitation::fromArray($row) : null;
    }

    public function findByTenantId(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invitations WHERE tenant_id = :tenant_id ORDER BY created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();
        return array_map(static fn(array $row): Invitation => Invitation::fromArray($row), $rows);
    }

    public function update(Invitation $invitation): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invitations SET accepted_at = :accepted_at WHERE id = :id'
        );
        $stmt->execute([
            'id'          => $invitation->getId(),
            'accepted_at' => $invitation->getAcceptedAt()?->format('Y-m-d H:i:s'),
        ]);
    }
}
