<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class SQLiteRefreshTokenRepository extends AbstractRepository implements RefreshTokenRepositoryInterface
{
    public function save(string $userId, string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (id, user_id, token_hash, expires_at)
             VALUES (:id, :user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'id'         => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, expires_at FROM refresh_tokens WHERE token_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function deleteByHash(string $tokenHash): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE token_hash = :hash');
        $stmt->execute(['hash' => $tokenHash]);
    }

    public function deleteExpired(): void
    {
        // SQLite-compatible: use datetime('now') instead of NOW()
        $this->pdo->exec("DELETE FROM refresh_tokens WHERE expires_at < datetime('now')");
    }

    public function deleteByUserId(string $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
