<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

use ZenCoParent\Application\User\UserDTO;
use ZenCoParent\Domain\Auth\OAuthAccountRepositoryInterface;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;
use ZenCoParent\Infrastructure\Auth\GoogleOAuthService;
use ZenCoParent\Infrastructure\Auth\JWTService;

final class OAuthGoogleHandler
{
    public function __construct(
        private UserRepositoryInterface         $userRepo,
        private TenantRepositoryInterface       $tenantRepo,
        private OAuthAccountRepositoryInterface $oauthRepo,
        private RefreshTokenRepositoryInterface $refreshRepo,
        private GoogleOAuthService              $googleOAuth,
        private JWTService                      $jwt,
    ) {}

    public function handle(OAuthGoogleCommand $command): LoginResult
    {
        // 1. Find tenant by slug
        $tenant = $this->tenantRepo->findBySlug($command->tenantSlug)
            ?? throw NotFoundException::forEntity('Tenant', $command->tenantSlug);

        // 2. Call GoogleOAuthService::getUserInfo($command->code) to get {id, email, firstName, lastName}
        $googleInfo = $this->googleOAuth->getUserInfo($command->code);
        $googleId   = (string) $googleInfo['id'];
        $email      = (string) $googleInfo['email'];
        $firstName  = (string) ($googleInfo['firstName'] ?? '');
        $lastName   = (string) ($googleInfo['lastName'] ?? '');

        // 3. Check OAuthAccountRepository::findByProviderAndProviderId('google', $googleId)
        $oauthAccount = $this->oauthRepo->findByProviderAndProviderId('google', $googleId);

        if ($oauthAccount !== null) {
            // 4. If found: load user, update last_login
            $user = $this->userRepo->findById($oauthAccount['user_id'])
                ?? throw NotFoundException::forEntity('User', $oauthAccount['user_id']);

            if (!$user->isActive()) {
                throw UnauthorizedException::create('Account is disabled');
            }

            $user = $user->withLastLogin();
            $this->userRepo->update($user);
        } else {
            // 5. If not found:
            $existingUser = $this->userRepo->findByEmail($tenant->getId(), $email);

            if ($existingUser !== null) {
                // 5a. Check if user with email already exists in tenant → link OAuth to existing user
                $user = $existingUser;

                if (!$user->isActive()) {
                    throw UnauthorizedException::create('Account is disabled');
                }

                $this->oauthRepo->save($user->getId(), 'google', $googleId);

                $user = $user->withLastLogin();
                $this->userRepo->update($user);
            } else {
                // 5b. If no user: create new user (no password, role=parent), then save OAuth account
                $user = User::create(
                    tenantId:     $tenant->getId(),
                    email:        $email,
                    passwordHash: '',
                    firstName:    $firstName,
                    lastName:     $lastName,
                    role:         UserRole::Parent,
                );
                $this->userRepo->save($user);
                $this->oauthRepo->save($user->getId(), 'google', $googleId);

                $user = $user->withLastLogin();
                $this->userRepo->update($user);
            }
        }

        // 6. Generate tokens, store refresh token, return LoginResult
        $accessToken  = $this->jwt->generateAccessToken($user->getId(), $tenant->getId(), $user->getRole()->value);
        $refreshToken = $this->jwt->generateRefreshToken();
        $tokenHash    = $this->jwt->hashRefreshToken($refreshToken);

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
