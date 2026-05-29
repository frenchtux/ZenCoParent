<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Event\Event;
use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\Event\EventType;
use ZenCoParent\Domain\MedicalRecord\MedicalRecord;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\Shared\TransactionManagerInterface;

final class CreateEventHandler
{
    public function __construct(
        private EventRepositoryInterface        $eventRepo,
        private MedicalRecordRepositoryInterface $medicalRepo,
        private ChildRepositoryInterface        $childRepo,
        private TransactionManagerInterface     $txManager,
    ) {}

    public function handle(CreateEventCommand $command): EventDTO
    {
        // Validate title not empty
        if (trim($command->title) === '') {
            throw ValidationException::withErrors(['title' => 'Title is required.']);
        }

        // Validate type is a valid EventType
        $eventType = EventType::tryFrom($command->type);
        if ($eventType === null) {
            throw ValidationException::withErrors(['type' => 'Invalid event type. Must be one of: custody, activity, medical.']);
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

        // Validate endAt >= startAt (equal allowed for all-day / instantaneous events)
        if ($endAt < $startAt) {
            throw ValidationException::withErrors(['end_at' => 'La date de fin ne peut pas être antérieure à la date de début.']);
        }

        // Medical events: child_id is mandatory; report is optional (filled after the appointment)
        if ($eventType === EventType::Medical) {
            if ($command->childId === null) {
                throw ValidationException::withErrors(['child_id' => 'child_id is required for medical events']);
            }
        }

        // Verify child exists in tenant if childId provided
        if ($command->childId !== null) {
            $child = $this->childRepo->findById($command->childId);
            if ($child === null || $child->getTenantId() !== $command->tenantId) {
                throw NotFoundException::forEntity('Child', $command->childId);
            }
        }

        // Create Event entity
        $event = Event::create(
            tenantId:    $command->tenantId,
            title:       $command->title,
            type:        $eventType,
            startAt:     $startAt,
            endAt:       $endAt,
            allDay:      $command->allDay,
            createdBy:   $command->createdBy,
            childId:     $command->childId,
            description: $command->description,
        );

        $this->txManager->begin();

        try {
            $this->eventRepo->save($event);

            if ($eventType === EventType::Medical) {
                // Parse recordedAt or fall back to event's startAt
                $recordedAt = $event->getStartAt();
                if ($command->recordedAt !== null) {
                    try {
                        $recordedAt = new \DateTimeImmutable($command->recordedAt);
                    } catch (\Exception) {
                        throw ValidationException::withErrors(['recorded_at' => 'Invalid recorded date format. Must be ISO 8601.']);
                    }
                }

                $medicalRecord = MedicalRecord::create(
                    tenantId:     $command->tenantId,
                    childId:      $command->childId ?? '',
                    eventId:      $event->getId(),
                    report:       $command->report,
                    practitioner: $command->practitioner,
                    recordedAt:   $recordedAt,
                    createdBy:    $command->createdBy,
                );

                $this->medicalRepo->save($medicalRecord);
            }

            $this->txManager->commit();
        } catch (\Throwable $e) {
            $this->txManager->rollback();
            throw $e;
        }

        return EventDTO::fromEvent($event);
    }
}
