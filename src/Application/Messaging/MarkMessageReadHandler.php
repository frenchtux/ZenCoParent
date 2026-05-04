<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;

final class MarkMessageReadHandler
{
    public function __construct(
        private ThreadRepositoryInterface  $threadRepo,
        private MessageRepositoryInterface $messageRepo,
    ) {}

    public function handle(string $messageId, string $threadId, string $userId): void
    {
        // Verify user is a participant in the thread
        if (!$this->threadRepo->isParticipant($threadId, $userId)) {
            throw UnauthorizedException::create('You are not a participant of this thread');
        }

        $message = $this->messageRepo->findById($messageId)
            ?? throw NotFoundException::forEntity('Message', $messageId);

        // Idempotent: already read, nothing to do
        if ($message->isRead()) {
            return;
        }

        $this->messageRepo->markRead($messageId, new \DateTimeImmutable());
    }
}
