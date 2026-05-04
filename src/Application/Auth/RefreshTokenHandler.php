<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

use ZenCoParent\Application\User\UserDTO;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Infrastructure\Auth\JWTService;

final class RefreshTokenHandler
{
    public function __construct(
        private UserRepositoryInterface         $userRepo,
        private RefreshTokenRepositoryInterface $refreshRepo,
        private JWTService                      $jwt,
    ) {}

    public function handle(RefreshTokenCommand $command): LoginResult
    {
        // 1. Hash the incoming refresh token
        $tokenHash = $this->jwt->hashRefreshToken($command->refreshToken);

        // 2. Find by hash in DB — throw UnauthorizedException if not found
        $record = $this->refreshRepo->findByHash($tokenHash)
            ?? throw UnauthorizedException::create('Invalid refresh token');

        // 3. Check expiry — throw UnauthorizedException if expired
        $expiresAt = new \DateTimeImmutable($record['expires_at']);
        if ($expiresAt < new \DateTimeImmutable()) {
            $this->refreshRepo->deleteByHash($tokenHash);
            throw UnauthorizedException::create('Refresh token has expired');
        }

        // 4. Load user from DB — throw NotFoundException if not found
        $user = $this->userRepo->findById($record['user_id'])
            ?? throw NotFoundException::forEntity('User', $record['user_id']);

        // 5. Delete old refresh token (rotation)
        $this->refreshRepo->deleteByHash($tokenHash);

        // 6. Generate new access token + refresh token
        $accessToken      = $this->jwt->generateAccessToken($user->getId(), $user->getTenantId(), $user->getRole()->value);
        $newRefreshToken  = $this->jwt->generateRefreshToken();
        $newTokenHash     = $this->jwt->hashRefreshToken($newRefreshToken);

        // 7. Store new refresh token
        $expiry = (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000);
        $this->refreshRepo->save(
            $user->getId(),
            $newTokenHash,
            new \DateTimeImmutable("+{$expiry} seconds")
        );

        // 8. Return LoginResult
        return new LoginResult(
            accessToken:  $accessToken,
            refreshToken: $newRefreshToken,
            user:         UserDTO::fromUser($user),
        );
    }
}
