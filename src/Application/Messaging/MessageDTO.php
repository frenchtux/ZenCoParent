<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

final readonly class MessageDTO
{
    public function __construct(
        public string  $id,
        public string  $threadId,
        public string  $tenantId,
        public string  $senderId,
        public string  $content,
        public bool    $isRead,
        public ?string $readAt,
        public string  $createdAt,
    ) {}

    public static function fromMessage(\ZenCoParent\Domain\Messaging\Message $message): self
    {
        return new self(
            id:        $message->getId(),
            threadId:  $message->getThreadId(),
            tenantId:  $message->getTenantId(),
            senderId:  $message->getSenderId(),
            content:   $message->getContent(),
            isRead:    $message->isRead(),
            readAt:    $message->getReadAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'thread_id'  => $this->threadId,
            'tenant_id'  => $this->tenantId,
            'sender_id'  => $this->senderId,
            'content'    => $this->content,
            'is_read'    => $this->isRead,
            'read_at'    => $this->readAt,
            'created_at' => $this->createdAt,
        ];
    }
}
