<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;

final class ListThreadsHandler
{
    public function __construct(
        private ThreadRepositoryInterface  $threadRepo,
        private MessageRepositoryInterface $messageRepo,
    ) {}

    /**
     * @return ThreadDTO[]
     */
    public function handle(string $userId, string $tenantId): array
    {
        $threads = $this->threadRepo->findByUserId($userId, $tenantId);

        return array_map(function ($thread) use ($userId) {
            $unread = $this->messageRepo->countUnread($thread->getId(), $userId);
            return ThreadDTO::fromThread($thread, $unread);
        }, $threads);
    }
}
