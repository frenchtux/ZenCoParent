<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class GetEventHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepo,
    ) {}

    public function handle(string $id, string $tenantId): EventDTO
    {
        $event = $this->eventRepo->findById($id)
            ?? throw NotFoundException::forEntity('Event', $id);

        if ($event->getTenantId() !== $tenantId) {
            throw NotFoundException::forEntity('Event', $id);
        }

        return EventDTO::fromEvent($event);
    }
}
