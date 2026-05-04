<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

final readonly class CreateEventCommand
{
    public function __construct(
        public string  $tenantId,
        public string  $title,
        public string  $type,        // 'custody' | 'activity' | 'medical'
        public string  $startAt,     // ISO 8601 string
        public string  $endAt,       // ISO 8601 string
        public bool    $allDay,
        public string  $createdBy,   // userId
        public ?string $childId      = null,
        public ?string $description  = null,
        // Medical fields (required when type = 'medical')
        public ?string $report       = null,
        public ?string $practitioner = null,
        public ?string $recordedAt   = null, // ISO 8601 string, defaults to startAt
    ) {}
}
