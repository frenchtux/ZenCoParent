<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Invitation;

use Psr\Log\LoggerInterface;
use ZenCoParent\Domain\Invitation\Invitation;
use ZenCoParent\Domain\Invitation\InvitationRepositoryInterface;
use ZenCoParent\Domain\Notification\MailerInterface;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;

final class CreateInvitationHandler
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepo,
        private ?TenantRepositoryInterface    $tenantRepo = null,
        private ?UserRepositoryInterface      $userRepo   = null,
        private MailerInterface               $mailer     = new \ZenCoParent\Infrastructure\Notification\NullMailer(),
        private LoggerInterface               $logger     = new \Psr\Log\NullLogger(),
        private string                        $appUrl     = '',
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

        // Send invitation email (best-effort)
        try {
            $inviter = null;
            if ($this->userRepo !== null) {
                $inviter = $this->userRepo->findById($command->invitedBy);
            }
            $family = null;
            if ($this->tenantRepo !== null) {
                $family = $this->tenantRepo->findById($command->tenantId);
            }
            $inviterName = $inviter?->getFullName() ?? 'Un parent';
            $familyName  = $family?->getName() ?? '';
            $inviteUrl   = rtrim($this->appUrl, '/') . '/frontend/invitation.html?token=' . $invitation->getToken();

            $this->mailer->sendInvitation(
                to:           $invitation->getEmail(),
                inviterName:  $inviterName,
                familyName:   $familyName,
                invitationUrl: $inviteUrl,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Could not send invitation email', ['error' => $e->getMessage()]);
        }

        return $invitation;
    }
}
