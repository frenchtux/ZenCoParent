<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Event;

interface EventRepositoryInterface
{
    public function findById(string $id): ?Event;

    /** @return Event[] */
    public function findByTenantId(
        string              $tenantId,
        ?string             $childId = null,
        ?string             $type = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array;

    public function save(Event $event): void;

    public function update(Event $event): void;

    public function delete(string $id): void;

    public function existsForTenant(string $id, string $tenantId): bool;

    /**
     * Return past medical events that have no associated medical_record.
     * "Past" = start_at is before $now.
     * Only returns events created by $createdBy (the parent who booked).
     *
     * @return Event[]
     */
    public function findPastMedicalWithoutReport(
        string             $tenantId,
        string             $createdBy,
        \DateTimeImmutable $now,
    ): array;
}
