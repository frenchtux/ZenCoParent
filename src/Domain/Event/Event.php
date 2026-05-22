<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Event;

final class Event
{
    public function __construct(
        private readonly string             $id,
        private readonly string             $tenantId,
        private readonly ?string            $childId,
        private readonly string             $title,
        private readonly ?string            $description,
        private readonly EventType          $type,
        private readonly \DateTimeImmutable $startAt,
        private readonly \DateTimeImmutable $endAt,
        private readonly bool               $allDay,
        private readonly ?string            $createdBy,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string             $tenantId,
        string             $title,
        EventType          $type,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        bool               $allDay,
        string             $createdBy,
        ?string            $childId = null,
        ?string            $description = null,
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            id:          \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:    $tenantId,
            childId:     $childId,
            title:       $title,
            description: $description,
            type:        $type,
            startAt:     $startAt,
            endAt:       $endAt,
            allDay:      $allDay,
            createdBy:   $createdBy,
            createdAt:   $now,
            updatedAt:   $now,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:          $data['id'],
            tenantId:    $data['tenant_id'],
            childId:     $data['child_id'] ?? null,
            title:       $data['title'],
            description: $data['description'] ?? null,
            type:        EventType::from($data['type']),
            startAt:     new \DateTimeImmutable($data['start_at']),
            endAt:       new \DateTimeImmutable($data['end_at']),
            allDay:      (bool) $data['all_day'],
            createdBy:   $data['created_by'] ?? null,
            createdAt:   new \DateTimeImmutable($data['created_at']),
            updatedAt:   new \DateTimeImmutable($data['updated_at']),
        );
    }

    public function withUpdated(
        string             $title,
        ?string            $description,
        EventType          $type,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        bool               $allDay,
        ?string            $childId,
    ): self {
        return new self(
            id:          $this->id,
            tenantId:    $this->tenantId,
            childId:     $childId,
            title:       $title,
            description: $description,
            type:        $type,
            startAt:     $startAt,
            endAt:       $endAt,
            allDay:      $allDay,
            createdBy:   $this->createdBy,
            createdAt:   $this->createdAt,
            updatedAt:   new \DateTimeImmutable(),
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

    public function getChildId(): ?string
    {
        return $this->childId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): EventType
    {
        return $this->type;
    }

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        $startAtStr = $this->startAt->format(\DateTimeInterface::ATOM);
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenantId,
            'child_id'    => $this->childId,
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type->value,
            'start_at'    => $startAtStr,
            'start_date'  => $this->startAt->format('Y-m-d'),
            'start_time'  => $this->allDay ? null : $this->startAt->format('H:i'),
            'date'        => $this->startAt->format('Y-m-d'),
            'end_at'      => $this->endAt->format(\DateTimeInterface::ATOM),
            'all_day'     => $this->allDay,
            'created_by'  => $this->createdBy,
            'created_at'  => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at'  => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
