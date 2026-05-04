<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Messaging\Message;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLMessageRepository extends AbstractRepository implements MessageRepositoryInterface
{
    public function findById(string $id): ?Message
    {
        $stmt = $this->pdo->prepare('SELECT * FROM messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Message::fromArray($row) : null;
    }

    public function findByThreadId(
        string              $threadId,
        ?\DateTimeImmutable $since = null,
        int                 $limit = 50,
    ): array {
        $params = ['thread_id' => $threadId];
        $sql    = 'SELECT * FROM messages WHERE thread_id = :thread_id';

        if ($since !== null) {
            $sql .= ' AND created_at > :since';
            $params['since'] = $since->format('Y-m-d H:i:s');
        }

        $sql .= ' ORDER BY created_at ASC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('thread_id', $params['thread_id']);
        if ($since !== null) {
            $stmt->bindValue('since', $params['since']);
        }
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return array_map(static fn(array $row): Message => Message::fromArray($row), $rows);
    }

    public function save(Message $message): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (id, thread_id, tenant_id, sender_id, content, read_at, created_at)
             VALUES (:id, :thread_id, :tenant_id, :sender_id, :content, :read_at, :created_at)'
        );
        $stmt->execute([
            'id'         => $message->getId(),
            'thread_id'  => $message->getThreadId(),
            'tenant_id'  => $message->getTenantId(),
            'sender_id'  => $message->getSenderId(),
            'content'    => $message->getContent(),
            'read_at'    => $message->getReadAt()?->format('Y-m-d H:i:s'),
            'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markRead(string $messageId, \DateTimeImmutable $readAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE messages SET read_at = :read_at WHERE id = :id'
        );
        $stmt->execute([
            'read_at' => $readAt->format('Y-m-d H:i:s'),
            'id'      => $messageId,
        ]);
    }

    public function countUnread(string $threadId, string $excludeSenderId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM messages
             WHERE thread_id = :thread_id AND read_at IS NULL AND sender_id != :sender_id'
        );
        $stmt->execute([
            'thread_id' => $threadId,
            'sender_id' => $excludeSenderId,
        ]);
        return (int) $stmt->fetchColumn();
    }
}
