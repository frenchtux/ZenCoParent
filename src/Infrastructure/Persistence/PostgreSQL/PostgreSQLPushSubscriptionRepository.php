<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Notification\PushSubscriptionRepositoryInterface;

final class PostgreSQLPushSubscriptionRepository implements PushSubscriptionRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function save(string $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): void
    {
        $this->pdo->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
             VALUES (:uid, :endpoint, :p256dh, :auth, :ua)
             ON CONFLICT (endpoint) DO UPDATE
                SET user_id = EXCLUDED.user_id, p256dh = EXCLUDED.p256dh, auth = EXCLUDED.auth,
                    user_agent = EXCLUDED.user_agent, updated_at = NOW()'
        )->execute([
            'uid'      => $userId,
            'endpoint' => $endpoint,
            'p256dh'   => $p256dh,
            'auth'     => $auth,
            'ua'       => $userAgent,
        ]);
    }

    public function delete(string $userId, string $endpoint): void
    {
        $this->pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = :uid AND endpoint = :endpoint')
            ->execute(['uid' => $userId, 'endpoint' => $endpoint]);
    }

    public function deleteByEndpoint(string $endpoint): void
    {
        $this->pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint')
            ->execute(['endpoint' => $endpoint]);
    }

    public function findByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($userIds));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
