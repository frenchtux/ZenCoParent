<?php
declare(strict_types=1);

namespace ZenCoParent\Application\MedicalRecord;

final readonly class CreateMedicalRecordCommand
{
    public function __construct(
        public string  $tenantId,
        public string  $childId,
        public string  $report,
        public string  $createdBy,
        public ?string $eventId      = null,
        public ?string $practitioner = null,
        public ?string $recordedAt   = null, // ISO 8601, defaults to now
    ) {}
}
