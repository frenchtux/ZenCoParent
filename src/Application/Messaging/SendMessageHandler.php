<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

use ZenCoParent\Domain\Messaging\Message;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class SendMessageHandler
{
    public function __construct(
        private ThreadRepositoryInterface  $threadRepo,
        private MessageRepositoryInterface $messageRepo,
    ) {}

    public function handle(SendMessageCommand $command): MessageDTO
    {
        // 1. Verify thread exists
        $thread = $this->threadRepo->findById($command->threadId)
            ?? throw NotFoundException::forEntity('Thread', $command->threadId);

        // 2. Verify sender is a participant
        if (!$this->threadRepo->isParticipant($command->threadId, $command->senderId)) {
            throw UnauthorizedException::create('You are not a participant of this thread');
        }

        // 3. Validate content is not empty
        if (trim($command->content) === '') {
            throw ValidationException::withErrors(['content' => 'Message content cannot be empty']);
        }

        // 4. Create and persist message
        $message = Message::create(
            $command->threadId,
            $command->tenantId,
            $command->senderId,
            trim($command->content),
        );

        $this->messageRepo->save($message);

        return MessageDTO::fromMessage($message);
    }
}
