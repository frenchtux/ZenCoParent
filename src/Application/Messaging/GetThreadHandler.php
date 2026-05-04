<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class GetThreadHandler
{
    public function __construct(
        private ThreadRepositoryInterface  $threadRepo,
        private MessageRepositoryInterface $messageRepo,
    ) {}

    public function handle(string $threadId, string $userId): ThreadDTO
    {
        $thread = $this->threadRepo->findById($threadId)
            ?? throw NotFoundException::forEntity('Thread', $threadId);

        // Hide thread existence from non-participants
        if (!in_array($userId, $thread->getParticipantIds(), true)) {
            throw NotFoundException::forEntity('Thread', $threadId);
        }

        $unread = $this->messageRepo->countUnread($threadId, $userId);

        return ThreadDTO::fromThread($thread, $unread);
    }
}
