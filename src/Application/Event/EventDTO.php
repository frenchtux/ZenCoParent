<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

use ZenCoParent\Domain\Event\Event;

final readonly class EventDTO
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public ?string $childId,
        public string  $title,
        public ?string $description,
        public string  $type,
        public string  $startAt,
        public string  $endAt,
        public bool    $allDay,
        public ?string $createdBy,
        public string  $createdAt,
        public string  $updatedAt,
    ) {}

    public static function fromEvent(Event $event): self
    {
        return new self(
            id:          $event->getId(),
            tenantId:    $event->getTenantId(),
            childId:     $event->getChildId(),
            title:       $event->getTitle(),
            description: $event->getDescription(),
            type:        $event->getType()->value,
            startAt:     $event->getStartAt()->format(\DateTimeInterface::ATOM),
            endAt:       $event->getEndAt()->format(\DateTimeInterface::ATOM),
            allDay:      $event->isAllDay(),
            createdBy:   $event->getCreatedBy(),
            createdAt:   $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt:   $event->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenantId,
            'child_id'    => $this->childId,
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type,
            'start_at'    => $this->startAt,
            'end_at'      => $this->endAt,
            'all_day'     => $this->allDay,
            'created_by'  => $this->createdBy,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
