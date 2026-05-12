<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\Messaging;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\Messaging\MessageDTO;
use ZenCoParent\Application\Messaging\SendMessageCommand;
use ZenCoParent\Application\Messaging\SendMessageHandler;
use ZenCoParent\Domain\Messaging\Message;
use ZenCoParent\Domain\Messaging\Thread;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Messaging\ThreadType;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class SendMessageHandlerTest extends TestCase
{
    private MockInterface $threadRepo;
    private MockInterface $messageRepo;
    private SendMessageHandler $handler;

    private string $tenantId  = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId    = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $threadId  = 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->threadRepo  = Mockery::mock(ThreadRepositoryInterface::class);
        $this->messageRepo = Mockery::mock(MessageRepositoryInterface::class);
        $this->handler     = new SendMessageHandler($this->threadRepo, $this->messageRepo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_sends_message_to_thread(): void
    {
        $thread = Thread::create($this->tenantId, ThreadType::Parents, [$this->userId]);

        $this->threadRepo->shouldReceive('findById')->with($thread->getId())->andReturn($thread);
        $this->threadRepo->shouldReceive('isParticipant')->with($thread->getId(), $this->userId)->andReturn(true);
        $this->messageRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new SendMessageCommand(
            threadId: $thread->getId(),
            tenantId: $this->tenantId,
            senderId: $this->userId,
            content:  'Hello!',
        ));

        $this->assertInstanceOf(MessageDTO::class, $result);
        $this->assertSame('Hello!', $result->content);
        $this->assertSame($this->userId, $result->senderId);
        $this->assertFalse($result->isRead);
    }

    public function test_throws_not_found_for_unknown_thread(): void
    {
        $this->threadRepo->shouldReceive('findById')->with($this->threadId)->andReturn(null);

        $this->expectException(NotFoundException::class);

        $this->handler->handle(new SendMessageCommand(
            threadId: $this->threadId,
            tenantId: $this->tenantId,
            senderId: $this->userId,
            content:  'Hello!',
        ));
    }

    public function test_throws_unauthorized_for_non_participant(): void
    {
        $thread = Thread::create($this->tenantId, ThreadType::Parents, ['other-user-id']);

        $this->threadRepo->shouldReceive('findById')->with($thread->getId())->andReturn($thread);
        $this->threadRepo->shouldReceive('isParticipant')->with($thread->getId(), $this->userId)->andReturn(false);

        $this->expectException(UnauthorizedException::class);

        $this->handler->handle(new SendMessageCommand(
            threadId: $thread->getId(),
            tenantId: $this->tenantId,
            senderId: $this->userId,
            content:  'Hello!',
        ));
    }

    public function test_throws_validation_for_empty_content(): void
    {
        $thread = Thread::create($this->tenantId, ThreadType::Parents, [$this->userId]);

        $this->threadRepo->shouldReceive('findById')->with($thread->getId())->andReturn($thread);
        $this->threadRepo->shouldReceive('isParticipant')->with($thread->getId(), $this->userId)->andReturn(true);

        $this->expectException(ValidationException::class);

        $this->handler->handle(new SendMessageCommand(
            threadId: $thread->getId(),
            tenantId: $this->tenantId,
            senderId: $this->userId,
            content:  '   ',
        ));
    }
}
