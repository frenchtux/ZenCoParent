<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

use ZenCoParent\Application\User\UserDTO;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Infrastructure\Auth\JWTService;

final class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface         $userRepo,
        private TenantRepositoryInterface       $tenantRepo,
        private RefreshTokenRepositoryInterface $refreshRepo,
        private JWTService                      $jwt,
    ) {}

    public function handle(LoginCommand $command): LoginResult
    {
        // 1. Find tenant by slug — throw NotFoundException if not found
        $tenant = $this->tenantRepo->findBySlug($command->tenantSlug)
            ?? throw NotFoundException::forEntity('Tenant', $command->tenantSlug);

        // 2. Find user by email in tenant — throw UnauthorizedException if not found
        $user = $this->userRepo->findByEmail($tenant->getId(), $command->email)
            ?? throw UnauthorizedException::create('Invalid credentials');

        // 3. Verify password — throw UnauthorizedException if invalid
        if ($user->getPasswordHash() === null ||
            !password_verify($command->password, $user->getPasswordHash())) {
            throw UnauthorizedException::create('Invalid credentials');
        }

        // 4. Check user is active
        if (!$user->isActive()) {
            throw UnauthorizedException::create('Account is disabled');
        }

        // 5. Update last login
        $user = $user->withLastLogin();
        $this->userRepo->update($user);

        // 6. Generate tokens
        $accessToken  = $this->jwt->generateAccessToken($user->getId(), $tenant->getId(), $user->getRole()->value);
        $refreshToken = $this->jwt->generateRefreshToken();
        $tokenHash    = $this->jwt->hashRefreshToken($refreshToken);

        // 7. Store refresh token (expires in JWT_REFRESH_EXPIRY seconds)
        $expiry = (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000);
        $this->refreshRepo->save(
            $user->getId(),
            $tokenHash,
            new \DateTimeImmutable("+{$expiry} seconds")
        );

        return new LoginResult(
            accessToken:  $accessToken,
            refreshToken: $refreshToken,
            user:         UserDTO::fromUser($user),
        );
    }
}
