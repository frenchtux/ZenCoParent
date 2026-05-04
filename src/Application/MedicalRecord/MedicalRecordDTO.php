<?php
declare(strict_types=1);

namespace ZenCoParent\Application\MedicalRecord;

use ZenCoParent\Domain\MedicalRecord\MedicalRecord;

final readonly class MedicalRecordDTO
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public string  $childId,
        public ?string $eventId,
        public string  $report,
        public ?string $practitioner,
        public string  $recordedAt,
        public ?string $createdBy,
        public string  $createdAt,
    ) {}

    public static function fromRecord(MedicalRecord $record): self
    {
        return new self(
            id:           $record->getId(),
            tenantId:     $record->getTenantId(),
            childId:      $record->getChildId(),
            eventId:      $record->getEventId(),
            report:       $record->getReport(),
            practitioner: $record->getPractitioner(),
            recordedAt:   $record->getRecordedAt()->format(\DateTimeInterface::ATOM),
            createdBy:    $record->getCreatedBy(),
            createdAt:    $record->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'tenant_id'    => $this->tenantId,
            'child_id'     => $this->childId,
            'event_id'     => $this->eventId,
            'report'       => $this->report,
            'practitioner' => $this->practitioner,
            'recorded_at'  => $this->recordedAt,
            'created_by'   => $this->createdBy,
            'created_at'   => $this->createdAt,
        ];
    }
}
