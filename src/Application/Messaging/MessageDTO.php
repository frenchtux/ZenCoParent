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
            'id'        => $this->id,
            'threadId'  => $this->threadId,
            'tenantId'  => $this->tenantId,
            'senderId'  => $this->senderId,
            'content'   => $this->content,
            'isRead'    => $this->isRead,
            'readAt'    => $this->readAt,
            'createdAt' => $this->createdAt,
        ];
    }
}
