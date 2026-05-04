<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Messaging;

interface MessageRepositoryInterface
{
    public function findById(string $id): ?Message;

    /**
     * @return Message[] ordered by created_at ASC
     *
     * @param \DateTimeImmutable|null $since only return messages created after this timestamp
     * @param int                     $limit max messages to return (default 50)
     */
    public function findByThreadId(
        string               $threadId,
        ?\DateTimeImmutable  $since = null,
        int                  $limit = 50,
    ): array;

    public function save(Message $message): void;

    public function markRead(string $messageId, \DateTimeImmutable $readAt): void;

    /**
     * Count messages without read_at in this thread, excluding messages sent by $excludeSenderId.
     */
    public function countUnread(string $threadId, string $excludeSenderId): int;
}
