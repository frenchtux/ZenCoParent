<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Messaging;

final class Thread
{
    public function __construct(
        private readonly string             $id,
        private readonly string             $tenantId,
        private readonly ThreadType         $type,
        private readonly \DateTimeImmutable $createdAt,
        private readonly array              $participantIds,
    ) {}

    public static function create(string $tenantId, ThreadType $type, array $participantIds = []): self
    {
        return new self(
            id:             \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:       $tenantId,
            type:           $type,
            createdAt:      new \DateTimeImmutable(),
            participantIds: $participantIds,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:             $data['id'],
            tenantId:       $data['tenant_id'],
            type:           ThreadType::from($data['type']),
            createdAt:      new \DateTimeImmutable($data['created_at']),
            participantIds: $data['participant_ids'] ?? [],
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getType(): ThreadType
    {
        return $this->type;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getParticipantIds(): array
    {
        return $this->participantIds;
    }

    public function withParticipantAdded(string $userId): self
    {
        if (in_array($userId, $this->participantIds, true)) {
            return $this;
        }

        return new self(
            id:             $this->id,
            tenantId:       $this->tenantId,
            type:           $this->type,
            createdAt:      $this->createdAt,
            participantIds: [...$this->participantIds, $userId],
        );
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'tenant_id'       => $this->tenantId,
            'type'            => $this->type->value,
            'created_at'      => $this->createdAt->format(\DateTimeInterface::ATOM),
            'participant_ids' => $this->participantIds,
        ];
    }
}
