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
        $data = $message->toArray();

        return new self(
            id:        $data['id'],
            threadId:  $data['threadId'],
            tenantId:  $data['tenantId'],
            senderId:  $data['senderId'],
            content:   $data['content'],
            isRead:    $message->isRead(),
            readAt:    isset($data['readAt']) && $data['readAt'] instanceof \DateTimeInterface
                           ? $data['readAt']->format(\DateTimeInterface::ATOM)
                           : (is_string($data['readAt'] ?? null) ? $data['readAt'] : null),
            createdAt: $data['createdAt'] instanceof \DateTimeInterface
                           ? $data['createdAt']->format(\DateTimeInterface::ATOM)
                           : (string) $data['createdAt'],
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
