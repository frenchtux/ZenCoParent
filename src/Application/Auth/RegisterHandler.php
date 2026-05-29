<?php

declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

use Psr\Log\LoggerInterface;
use ZenCoParent\Application\User\UserDTO;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Notification\MailerInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\Tenant\Tenant;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;
use ZenCoParent\Infrastructure\Auth\JWTService;

final class RegisterHandler
{
    public function __construct(
        private TenantRepositoryInterface       $tenantRepo,
        private UserRepositoryInterface         $userRepo,
        private RefreshTokenRepositoryInterface $refreshRepo,
        private JWTService                      $jwt,
        private MailerInterface                 $mailer  = new \ZenCoParent\Infrastructure\Notification\NullMailer(),
        private LoggerInterface                 $logger  = new \Psr\Log\NullLogger(),
    ) {}

    public function handle(RegisterCommand $command): LoginResult
    {
        // Validate email
        if (!filter_var($command->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse e-mail invalide.');
        }

        // Validate password length
        if (strlen($command->password) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        // Build slug from family name
        $slug = $this->buildSlug($command->familyName);

        if ($slug === '') {
            throw new \InvalidArgumentException('Le nom de famille est invalide.');
        }

        // Ensure slug uniqueness
        $existing = $this->tenantRepo->findBySlug($slug);
        if ($existing !== null) {
            // Append a random suffix to make it unique
            $slug = $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
        }

        // Create tenant
        $tenant = Tenant::create(trim($command->familyName), $slug);
        $this->tenantRepo->save($tenant);

        // Create the registering user as parent (not admin)
        // Admins are provisioned separately by a super-admin or via the seeder.
        $passwordHash = password_hash($command->password, PASSWORD_BCRYPT);
        $user = User::create(
            tenantId:     $tenant->getId(),
            email:        $command->email,
            passwordHash: $passwordHash,
            firstName:    $command->firstName,
            lastName:     $command->lastName,
            role:         UserRole::Parent,
        );
        $this->userRepo->save($user);

        // Mint JWT
        $accessToken  = $this->jwt->generateAccessToken($user->getId(), $tenant->getId(), $user->getRole()->value);
        $refreshToken = $this->jwt->generateRefreshToken();
        $tokenHash    = $this->jwt->hashRefreshToken($refreshToken);

        $expiry = (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000);
        $this->refreshRepo->save(
            $user->getId(),
            $tokenHash,
            new \DateTimeImmutable("+{$expiry} seconds"),
        );

        // Send welcome email (best-effort — never block registration)
        try {
            $this->mailer->sendWelcome($user->getEmail(), $user->getFirstName(), $tenant->getName());
        } catch (\Throwable $e) {
            $this->logger->warning('Could not send welcome email', ['error' => $e->getMessage()]);
        }

        return new LoginResult(
            accessToken:  $accessToken,
            refreshToken: $refreshToken,
            user:         UserDTO::fromUser($user),
        );
    }

    private function buildSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        // Replace accented characters
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        // Replace spaces with dashes
        $slug = str_replace(' ', '-', $slug);
        // Strip anything that isn't alphanumeric or dash
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        // Collapse multiple dashes
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
