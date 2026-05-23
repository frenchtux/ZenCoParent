<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Invitation;

interface InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void;

    public function findByToken(string $token): ?Invitation;

    public function findByTenantId(string $tenantId): array;

    public function update(Invitation $invitation): void;
}
