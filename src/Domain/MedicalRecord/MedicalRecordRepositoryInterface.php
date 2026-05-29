<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\MedicalRecord;

interface MedicalRecordRepositoryInterface
{
    public function findById(string $id): ?MedicalRecord;

    /** @return MedicalRecord[] — ordered by recorded_at DESC */
    public function findByChildId(string $childId, string $tenantId): array;

    public function findByEventId(string $eventId): ?MedicalRecord;

    public function save(MedicalRecord $record): void;

    public function delete(string $id): void;
}
