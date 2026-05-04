<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Auth\OAuthAccountRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLOAuthAccountRepository extends AbstractRepository implements OAuthAccountRepositoryInterface
{
    public function findByProviderAndProviderId(string $provider, string $providerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, provider, provider_id
             FROM oauth_accounts
             WHERE provider = :provider AND provider_id = :provider_id
             LIMIT 1'
        );
        $stmt->execute([
            'provider'    => $provider,
            'provider_id' => $providerId,
        ]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function save(string $userId, string $provider, string $providerId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_accounts (id, user_id, provider, provider_id)
             VALUES (:id, :user_id, :provider, :provider_id)'
        );
        $stmt->execute([
            'id'          => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'user_id'     => $userId,
            'provider'    => $provider,
            'provider_id' => $providerId,
        ]);
    }
}
