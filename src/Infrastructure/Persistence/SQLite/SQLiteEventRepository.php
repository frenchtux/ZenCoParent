<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Event\Event;
use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class SQLiteEventRepository extends AbstractRepository implements EventRepositoryInterface
{
    public function findById(string $id): ?Event
    {
        $stmt = $this->pdo->prepare('SELECT * FROM events WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Event::fromArray($row) : null;
    }

    public function findByTenantId(
        string              $tenantId,
        ?string             $childId = null,
        ?string             $type = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $params = ['tenant_id' => $tenantId];
        $sql    = 'SELECT * FROM events WHERE tenant_id = :tenant_id';

        if ($childId !== null) {
            $sql .= ' AND child_id = :child_id';
            $params['child_id'] = $childId;
        }

        if ($type !== null) {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }

        if ($from !== null) {
            $sql .= ' AND start_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to !== null) {
            $sql .= ' AND start_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:s');
        }

        $sql .= ' ORDER BY start_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static fn(array $row): Event => Event::fromArray($row), $rows);
    }

    public function save(Event $event): void
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO events (
                id, tenant_id, child_id, title, description,
                type, start_at, end_at, all_day,
                created_by, created_at, updated_at
            ) VALUES (
                :id, :tenant_id, :child_id, :title, :description,
                :type, :start_at, :end_at, :all_day,
                :created_by, :created_at, :updated_at
            )'
        );
        $stmt->execute([
            'id'          => $event->getId(),
            'tenant_id'   => $event->getTenantId(),
            'child_id'    => $event->getChildId(),
            'title'       => $event->getTitle(),
            'description' => $event->getDescription(),
            'type'        => $event->getType()->value,
            'start_at'    => $event->getStartAt()->format('Y-m-d H:i:s'),
            'end_at'      => $event->getEndAt()->format('Y-m-d H:i:s'),
            'all_day'     => $event->isAllDay() ? 1 : 0,
            'created_by'  => $event->getCreatedBy(),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function update(Event $event): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE events SET
                child_id    = :child_id,
                title       = :title,
                description = :description,
                type        = :type,
                start_at    = :start_at,
                end_at      = :end_at,
                all_day     = :all_day,
                updated_at  = :updated_at
            WHERE id = :id'
        );
        $stmt->execute([
            'id'          => $event->getId(),
            'child_id'    => $event->getChildId(),
            'title'       => $event->getTitle(),
            'description' => $event->getDescription(),
            'type'        => $event->getType()->value,
            'start_at'    => $event->getStartAt()->format('Y-m-d H:i:s'),
            'end_at'      => $event->getEndAt()->format('Y-m-d H:i:s'),
            'all_day'     => $event->isAllDay() ? 1 : 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function existsForTenant(string $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM events WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id'        => $id,
            'tenant_id' => $tenantId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
