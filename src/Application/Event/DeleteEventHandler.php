<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Event;

use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class DeleteEventHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepo,
    ) {}

    public function handle(string $id, string $tenantId): void
    {
        if (!$this->eventRepo->existsForTenant($id, $tenantId)) {
            throw NotFoundException::forEntity('Event', $id);
        }

        $this->eventRepo->delete($id);
    }
}
