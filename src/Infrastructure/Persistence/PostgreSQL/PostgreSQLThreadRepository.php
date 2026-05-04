<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Messaging\Thread;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLThreadRepository extends AbstractRepository implements ThreadRepositoryInterface
{
    public function findById(string $id): ?Thread
    {
        $stmt = $this->pdo->prepare('SELECT * FROM threads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $participantIds = $this->getParticipantIds($id);

        return Thread::fromArray([...$row, 'participant_ids' => $participantIds]);
    }

    public function findByUserId(string $userId, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM threads t
             INNER JOIN thread_participants tp ON tp.thread_id = t.id
             WHERE tp.user_id = :user_id AND t.tenant_id = :tenant_id
             ORDER BY t.created_at DESC'
        );
        $stmt->execute([
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
        ]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return [];
        }

        $threadIds = array_column($rows, 'id');

        $placeholders = implode(',', array_map(static fn(int $i): string => ':tid' . $i, array_keys($threadIds)));
        $params       = [];
        foreach ($threadIds as $i => $tid) {
            $params['tid' . $i] = $tid;
        }

        $pStmt = $this->pdo->prepare(
            'SELECT thread_id, user_id FROM thread_participants WHERE thread_id IN (' . $placeholders . ')'
        );
        $pStmt->execute($params);
        $participantRows = $pStmt->fetchAll();

        $participantMap = [];
        foreach ($participantRows as $pr) {
            $participantMap[$pr['thread_id']][] = $pr['user_id'];
        }

        return array_map(
            static fn(array $row): Thread => Thread::fromArray(
                [...$row, 'participant_ids' => $participantMap[$row['id']] ?? []]
            ),
            $rows
        );
    }

    public function save(Thread $thread): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO threads (id, tenant_id, type, created_at)
             VALUES (:id, :tenant_id, :type, :created_at)'
        );
        $stmt->execute([
            'id'         => $thread->getId(),
            'tenant_id'  => $thread->getTenantId(),
            'type'       => $thread->getType()->value,
            'created_at' => $thread->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        foreach ($thread->getParticipantIds() as $userId) {
            $this->addParticipant($thread->getId(), $userId);
        }
    }

    public function addParticipant(string $threadId, string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO thread_participants (thread_id, user_id)
             VALUES (:thread_id, :user_id)
             ON CONFLICT DO NOTHING'
        );
        $stmt->execute([
            'thread_id' => $threadId,
            'user_id'   => $userId,
        ]);
    }

    public function isParticipant(string $threadId, string $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM thread_participants WHERE thread_id = :thread_id AND user_id = :user_id'
        );
        $stmt->execute([
            'thread_id' => $threadId,
            'user_id'   => $userId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getParticipantIds(string $threadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM thread_participants WHERE thread_id = :thread_id'
        );
        $stmt->execute(['thread_id' => $threadId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
