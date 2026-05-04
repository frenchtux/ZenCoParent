<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

final readonly class UpdateEventCommand
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public string  $title,
        public string  $type,
        public string  $startAt,
        public string  $endAt,
        public bool    $allDay,
        public ?string $childId     = null,
        public ?string $description = null,
    ) {}
}
