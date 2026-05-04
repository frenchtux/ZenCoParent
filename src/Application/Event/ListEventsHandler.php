<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

use ZenCoParent\Domain\Event\EventRepositoryInterface;

final class ListEventsHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepo,
    ) {}

    /**
     * @return EventDTO[]
     */
    public function handle(
        string  $tenantId,
        ?string $childId = null,
        ?string $type    = null,
        ?string $from    = null,  // ISO 8601 string or null
        ?string $to      = null,  // ISO 8601 string or null
    ): array {
        $fromDate = $from ? new \DateTimeImmutable($from) : null;
        $toDate   = $to   ? new \DateTimeImmutable($to)   : null;

        $events = $this->eventRepo->findByTenantId($tenantId, $childId, $type, $fromDate, $toDate);

        return array_map(fn($e) => EventDTO::fromEvent($e), $events);
    }
}
