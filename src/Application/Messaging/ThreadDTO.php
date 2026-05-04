<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

final readonly class ThreadDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $type,
        public array  $participantIds,
        public string $createdAt,
        public int    $unreadCount,
    ) {}

    public static function fromThread(
        \ZenCoParent\Domain\Messaging\Thread $thread,
        int $unreadCount = 0,
    ): self {
        return new self(
            id:             $thread->getId(),
            tenantId:       $thread->getTenantId(),
            type:           $thread->getType()->value,
            participantIds: $thread->getParticipantIds(),
            createdAt:      $thread->getCreatedAt()->format(\DateTimeInterface::ATOM),
            unreadCount:    $unreadCount,
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'tenantId'       => $this->tenantId,
            'type'           => $this->type,
            'participantIds' => $this->participantIds,
            'createdAt'      => $this->createdAt,
            'unreadCount'    => $this->unreadCount,
        ];
    }
}
