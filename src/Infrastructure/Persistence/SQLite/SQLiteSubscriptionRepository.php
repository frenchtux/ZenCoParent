<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Subscription\Subscription;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;

/**
 * Community stub — subscriptions do not exist in Community mode.
 * All write methods are no-ops; all read methods return null/empty.
 */
final class SQLiteSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findByTenantId(string $tenantId): ?Subscription
    {
        return null;
    }

    public function findByStripeSubscriptionId(string $stripeId): ?Subscription
    {
        return null;
    }

    public function save(Subscription $subscription): void {}

    public function update(string $id, array $fields): void {}

    public function findByTenantIds(array $tenantIds): array
    {
        return [];
    }

    public function getMetrics(): array
    {
        return ['total' => 0, 'active' => 0, 'trial' => 0, 'past_due' => 0, 'mrr_cents' => 0];
    }
}
