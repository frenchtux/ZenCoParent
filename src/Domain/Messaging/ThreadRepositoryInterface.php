<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Messaging;

interface ThreadRepositoryInterface
{
    public function findById(string $id): ?Thread;

    /**
     * @return Thread[] threads where $userId is a participant
     */
    public function findByUserId(string $userId, string $tenantId): array;

    public function save(Thread $thread): void;

    public function addParticipant(string $threadId, string $userId): void;

    public function isParticipant(string $threadId, string $userId): bool;

    /**
     * @return string[] user IDs
     */
    public function getParticipantIds(string $threadId): array;
}
