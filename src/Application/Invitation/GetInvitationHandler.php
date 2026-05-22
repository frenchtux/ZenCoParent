<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Invitation;

use ZenCoParent\Domain\Invitation\InvitationRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;

final class GetInvitationHandler
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepo,
        private TenantRepositoryInterface     $tenantRepo,
    ) {}

    public function handle(string $token): array
    {
        $invitation = $this->invitationRepo->findByToken($token)
            ?? throw NotFoundException::forEntity('Invitation', $token);

        if ($invitation->isExpired()) {
            throw new \DomainException("Cette invitation a expiré.");
        }

        if ($invitation->isAccepted()) {
            throw new \DomainException("Cette invitation a déjà été utilisée.");
        }

        $tenant = $this->tenantRepo->findById($invitation->getTenantId());

        $data = $invitation->toArray();
        $data['tenant_name'] = $tenant?->getName() ?? '';

        return $data;
    }
}
