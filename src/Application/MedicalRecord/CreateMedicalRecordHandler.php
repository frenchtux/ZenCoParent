<?php
declare(strict_types=1);

namespace ZenCoParent\Application\MedicalRecord;

use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\Event\EventType;
use ZenCoParent\Domain\MedicalRecord\MedicalRecord;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class CreateMedicalRecordHandler
{
    public function __construct(
        private MedicalRecordRepositoryInterface $medicalRepo,
        private ChildRepositoryInterface         $childRepo,
        private EventRepositoryInterface         $eventRepo,
    ) {}

    public function handle(CreateMedicalRecordCommand $command): MedicalRecordDTO
    {
        // Validate report not empty
        if (trim($command->report) === '') {
            throw ValidationException::withErrors(['report' => 'Report is required.']);
        }

        // Verify child exists and belongs to tenant
        $child = $this->childRepo->findById($command->childId);
        if ($child === null || $child->getTenantId() !== $command->tenantId) {
            throw NotFoundException::forEntity('Child', $command->childId);
        }

        // If eventId provided: verify event exists, belongs to tenant, and is type medical
        if ($command->eventId !== null) {
            $event = $this->eventRepo->findById($command->eventId);
            if ($event === null || $event->getTenantId() !== $command->tenantId) {
                throw NotFoundException::forEntity('Event', $command->eventId);
            }

            if ($event->getType() !== EventType::Medical) {
                throw ValidationException::withErrors(['event_id' => 'Event must be of type medical']);
            }
        }

        // Parse recordedAt if provided, or default to now
        $recordedAt = new \DateTimeImmutable();
        if ($command->recordedAt !== null) {
            try {
                $recordedAt = new \DateTimeImmutable($command->recordedAt);
            } catch (\Exception) {
                throw ValidationException::withErrors(['recorded_at' => 'Invalid recorded date format. Must be ISO 8601.']);
            }
        }

        $medicalRecord = MedicalRecord::create(
            tenantId:     $command->tenantId,
            childId:      $command->childId,
            eventId:      $command->eventId,
            report:       $command->report,
            practitioner: $command->practitioner,
            recordedAt:   $recordedAt,
            createdBy:    $command->createdBy,
        );

        $this->medicalRepo->save($medicalRecord);

        return MedicalRecordDTO::fromRecord($medicalRecord);
    }
}
