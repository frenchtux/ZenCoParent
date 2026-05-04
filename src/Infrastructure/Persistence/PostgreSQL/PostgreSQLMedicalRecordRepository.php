<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\MedicalRecord\MedicalRecord;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLMedicalRecordRepository extends AbstractRepository implements MedicalRecordRepositoryInterface
{
    public function findById(string $id): ?MedicalRecord
    {
        $stmt = $this->pdo->prepare('SELECT * FROM medical_records WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? MedicalRecord::fromArray($row) : null;
    }

    public function findByChildId(string $childId, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM medical_records
             WHERE child_id = :child_id AND tenant_id = :tenant_id
             ORDER BY recorded_at DESC'
        );
        $stmt->execute([
            'child_id'  => $childId,
            'tenant_id' => $tenantId,
        ]);
        $rows = $stmt->fetchAll();
        return array_map(static fn(array $row): MedicalRecord => MedicalRecord::fromArray($row), $rows);
    }

    public function findByEventId(string $eventId): ?MedicalRecord
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM medical_records WHERE event_id = :event_id LIMIT 1'
        );
        $stmt->execute(['event_id' => $eventId]);
        $row = $stmt->fetch();
        return $row !== false ? MedicalRecord::fromArray($row) : null;
    }

    public function save(MedicalRecord $record): void
    {
        $now  = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO medical_records (
                id, tenant_id, child_id, event_id, report,
                practitioner, recorded_at, created_by, created_at
            ) VALUES (
                :id, :tenant_id, :child_id, :event_id, :report,
                :practitioner, :recorded_at, :created_by, :created_at
            )'
        );
        $stmt->execute([
            'id'           => $record->getId(),
            'tenant_id'    => $record->getTenantId(),
            'child_id'     => $record->getChildId(),
            'event_id'     => $record->getEventId(),
            'report'       => $record->getReport(),
            'practitioner' => $record->getPractitioner(),
            'recorded_at'  => $record->getRecordedAt()->format('Y-m-d H:i:s'),
            'created_by'   => $record->getCreatedBy(),
            'created_at'   => $now,
        ]);
    }
}
