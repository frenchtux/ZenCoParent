<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class ListMessagesHandler
{
    public function __construct(
        private ThreadRepositoryInterface  $threadRepo,
        private MessageRepositoryInterface $messageRepo,
    ) {}

    /**
     * @return MessageDTO[]
     */
    public function handle(
        string  $threadId,
        string  $userId,
        ?string $since = null,
        int     $limit = 50,
    ): array {
        // Verify thread exists and user is a participant
        $thread = $this->threadRepo->findById($threadId)
            ?? throw NotFoundException::forEntity('Thread', $threadId);

        if (!in_array($userId, $thread->getParticipantIds(), true)) {
            throw NotFoundException::forEntity('Thread', $threadId);
        }

        $sinceDate = $since !== null ? new \DateTimeImmutable($since) : null;
        $limit     = max(1, min(100, $limit));

        $messages = $this->messageRepo->findByThreadId($threadId, $sinceDate, $limit);

        return array_map(fn($m) => MessageDTO::fromMessage($m), $messages);
    }
}
