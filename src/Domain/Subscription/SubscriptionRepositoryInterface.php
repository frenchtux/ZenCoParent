<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Subscription;

interface SubscriptionRepositoryInterface
{
    public function findByTenantId(string $tenantId): ?Subscription;

    public function findByStripeSubscriptionId(string $stripeId): ?Subscription;

    public function save(Subscription $subscription): void;

    public function update(string $id, array $fields): void;

    /** @return array{total: int, active: int, trial: int, past_due: int, mrr_cents: int} */
    public function getMetrics(): array;
}
