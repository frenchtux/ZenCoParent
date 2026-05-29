<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\Event\EventType;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class UpdateEventHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepo,
    ) {}

    public function handle(UpdateEventCommand $command): EventDTO
    {
        // Find event by id and verify tenant ownership
        $event = $this->eventRepo->findById($command->id);
        if ($event === null || $event->getTenantId() !== $command->tenantId) {
            throw NotFoundException::forEntity('Event', $command->id);
        }

        // Validate title not empty
        if (trim($command->title) === '') {
            throw ValidationException::withErrors(['title' => 'Title is required.']);
        }

        // Validate type is a valid EventType
        $eventType = EventType::tryFrom($command->type);
        if ($eventType === null) {
            throw ValidationException::withErrors(['type' => 'Invalid event type. Must be one of: custody, activity, medical.']);
        }

        // Disallow changing type to medical via update (no report can be provided)
        if ($eventType === EventType::Medical && $event->getType() !== EventType::Medical) {
            throw ValidationException::withErrors(['type' => 'Cannot change event type to medical via update. Create a new medical event instead.']);
        }

        // Validate startAt is parseable
        try {
            $startAt = new \DateTimeImmutable($command->startAt);
        } catch (\Exception) {
            throw ValidationException::withErrors(['start_at' => 'Invalid start date format. Must be ISO 8601.']);
        }

        // Validate endAt is parseable
        try {
            $endAt = new \DateTimeImmutable($command->endAt);
        } catch (\Exception) {
            throw ValidationException::withErrors(['end_at' => 'Invalid end date format. Must be ISO 8601.']);
        }

        // Validate endAt >= startAt
        if ($endAt < $startAt) {
            throw ValidationException::withErrors(['end_at' => 'La date de fin ne peut pas être antérieure à la date de début.']);
        }

        $updatedEvent = $event->withUpdated(
            title:       $command->title,
            type:        $eventType,
            startAt:     $startAt,
            endAt:       $endAt,
            allDay:      $command->allDay,
            childId:     $command->childId,
            description: $command->description,
        );

        $this->eventRepo->update($updatedEvent);

        return EventDTO::fromEvent($updatedEvent);
    }
}
