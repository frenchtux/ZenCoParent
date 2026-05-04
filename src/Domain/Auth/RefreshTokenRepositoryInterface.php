<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Auth;

interface RefreshTokenRepositoryInterface
{
    public function save(string $userId, string $tokenHash, \DateTimeImmutable $expiresAt): void;

    /**
     * Returns array with keys 'user_id' and 'expires_at', or null if not found.
     */
    public function findByHash(string $tokenHash): ?array;

    public function deleteByHash(string $tokenHash): void;

    public function deleteExpired(): void;

    public function deleteByUserId(string $userId): void;
}
