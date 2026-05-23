<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Invitation;

use ZenCoParent\Domain\Invitation\Invitation;
use ZenCoParent\Domain\Invitation\InvitationRepositoryInterface;

final class CreateInvitationHandler
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepo,
    ) {}

    public function handle(CreateInvitationCommand $command): Invitation
    {
        if (!filter_var($command->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse e-mail invalide.');
        }

        $allowedRoles = ['parent', 'child'];
        if (!in_array($command->role, $allowedRoles, true)) {
            throw new \InvalidArgumentException('Rôle invalide. Valeurs acceptées : parent, child.');
        }

        $invitation = Invitation::create(
            tenantId:  $command->tenantId,
            invitedBy: $command->invitedBy,
            email:     $command->email,
            role:      $command->role,
        );

        $this->invitationRepo->save($invitation);

        return $invitation;
    }
}
