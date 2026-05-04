<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Messaging;

final class Message
{
    public function __construct(
        private readonly string              $id,
        private readonly string              $threadId,
        private readonly string              $tenantId,
        private readonly string              $senderId,
        private readonly string              $content,
        private readonly ?\DateTimeImmutable $readAt,
        private readonly \DateTimeImmutable  $createdAt,
    ) {}

    public static function create(
        string $threadId,
        string $tenantId,
        string $senderId,
        string $content,
    ): self {
        return new self(
            id:        \Ramsey\Uuid\Uuid::uuid4()->toString(),
            threadId:  $threadId,
            tenantId:  $tenantId,
            senderId:  $senderId,
            content:   $content,
            readAt:    null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:        $data['id'],
            threadId:  $data['thread_id'],
            tenantId:  $data['tenant_id'],
            senderId:  $data['sender_id'],
            content:   $data['content'],
            readAt:    isset($data['read_at']) && $data['read_at'] !== null
                           ? new \DateTimeImmutable($data['read_at'])
                           : null,
            createdAt: new \DateTimeImmutable($data['created_at']),
        );
    }

    public function withRead(\DateTimeImmutable $readAt): self
    {
        return new self(
            id:        $this->id,
            threadId:  $this->threadId,
            tenantId:  $this->tenantId,
            senderId:  $this->senderId,
            content:   $this->content,
            readAt:    $readAt,
            createdAt: $this->createdAt,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getThreadId(): string
    {
        return $this->threadId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'thread_id'  => $this->threadId,
            'tenant_id'  => $this->tenantId,
            'sender_id'  => $this->senderId,
            'content'    => $this->content,
            'read_at'    => $this->readAt?->format(\DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
