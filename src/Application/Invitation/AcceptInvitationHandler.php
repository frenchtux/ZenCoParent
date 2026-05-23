<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Invitation;

use ZenCoParent\Application\Auth\LoginResult;
use ZenCoParent\Application\User\UserDTO;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Invitation\InvitationRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;
use ZenCoParent\Infrastructure\Auth\JWTService;

final class AcceptInvitationHandler
{
    public function __construct(
        private InvitationRepositoryInterface   $invitationRepo,
        private UserRepositoryInterface         $userRepo,
        private RefreshTokenRepositoryInterface $refreshRepo,
        private JWTService                      $jwt,
    ) {}

    public function handle(AcceptInvitationCommand $command): LoginResult
    {
        $invitation = $this->invitationRepo->findByToken($command->token)
            ?? throw NotFoundException::forEntity('Invitation', $command->token);

        if ($invitation->isExpired()) {
            throw new \DomainException("Cette invitation a expiré.");
        }

        if ($invitation->isAccepted()) {
            throw new \DomainException("Cette invitation a déjà été utilisée.");
        }

        if (strlen($command->password) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        // Check email not already in tenant
        $existingUser = $this->userRepo->findByEmail($invitation->getTenantId(), $invitation->getEmail());
        if ($existingUser !== null) {
            throw new \DomainException("Un compte existe déjà pour cet e-mail dans cette famille.");
        }

        // Map role string to UserRole enum
        $role = match ($invitation->getRole()) {
            'child' => UserRole::Child,
            default => UserRole::Parent,
        };

        // Create user
        $passwordHash = password_hash($command->password, PASSWORD_BCRYPT);
        $user = User::create(
            tenantId:     $invitation->getTenantId(),
            email:        $invitation->getEmail(),
            passwordHash: $passwordHash,
            firstName:    $command->firstName,
            lastName:     $command->lastName,
            role:         $role,
        );
        $this->userRepo->save($user);

        // Mark invitation accepted
        $accepted = $invitation->withAccepted();
        $this->invitationRepo->update($accepted);

        // Mint JWT
        $accessToken  = $this->jwt->generateAccessToken($user->getId(), $user->getTenantId(), $user->getRole()->value);
        $refreshToken = $this->jwt->generateRefreshToken();
        $tokenHash    = $this->jwt->hashRefreshToken($refreshToken);

        $expiry = (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000);
        $this->refreshRepo->save(
            $user->getId(),
            $tokenHash,
            new \DateTimeImmutable("+{$expiry} seconds"),
        );

        return new LoginResult(
            accessToken:  $accessToken,
            refreshToken: $refreshToken,
            user:         UserDTO::fromUser($user),
        );
    }
}
