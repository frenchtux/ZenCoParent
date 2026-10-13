<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Notification;

interface PushSubscriptionRepositoryInterface
{
    /** Re-subscribing the same endpoint (e.g. another user on a shared device) moves it to the new user. */
    public function save(string $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): void;

    public function delete(string $userId, string $endpoint): void;

    public function deleteByEndpoint(string $endpoint): void;

    /**
     * @param string[] $userIds
     * @return array<array{endpoint: string, p256dh: string, auth: string}>
     */
    public function findByUserIds(array $userIds): array;
}
