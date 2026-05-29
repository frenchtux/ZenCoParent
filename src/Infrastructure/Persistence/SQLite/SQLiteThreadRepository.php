<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Messaging\Thread;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class SQLiteThreadRepository extends AbstractRepository implements ThreadRepositoryInterface
{
    public function findById(string $id): ?Thread
    {
        $stmt = $this->pdo->prepare('SELECT * FROM threads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['participant_ids'] = $this->getParticipantIds($id);
        return Thread::fromArray($row);
    }

    public function findByUserId(string $userId, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM threads t
             INNER JOIN thread_participants tp ON tp.thread_id = t.id
             WHERE tp.user_id = :user_id AND t.tenant_id = :tenant_id
             ORDER BY t.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): Thread {
            $row['participant_ids'] = $this->getParticipantIds($row['id']);
            return Thread::fromArray($row);
        }, $rows);
    }

    public function save(Thread $thread): void
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO threads (id, tenant_id, type, subject, created_at)
             VALUES (:id, :tenant_id, :type, :subject, :created_at)'
        );
        $stmt->execute([
            'id'         => $thread->getId(),
            'tenant_id'  => $thread->getTenantId(),
            'type'       => $thread->getType()->value,
            'subject'    => $thread->getSubject(),
            'created_at' => $now,
        ]);

        foreach ($thread->getParticipantIds() as $userId) {
            $this->addParticipant($thread->getId(), $userId);
        }
    }

    public function addParticipant(string $threadId, string $userId): void
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO thread_participants (thread_id, user_id, joined_at)
             VALUES (:thread_id, :user_id, :joined_at)'
        );
        $stmt->execute(['thread_id' => $threadId, 'user_id' => $userId, 'joined_at' => $now]);
    }

    public function isParticipant(string $threadId, string $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM thread_participants
             WHERE thread_id = :thread_id AND user_id = :user_id'
        );
        $stmt->execute(['thread_id' => $threadId, 'user_id' => $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getParticipantIds(string $threadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM thread_participants WHERE thread_id = :thread_id'
        );
        $stmt->execute(['thread_id' => $threadId]);
        return array_column($stmt->fetchAll(), 'user_id');
    }
}
