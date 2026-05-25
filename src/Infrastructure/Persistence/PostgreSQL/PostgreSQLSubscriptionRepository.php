<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Subscription\Subscription;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLSubscriptionRepository extends AbstractRepository implements SubscriptionRepositoryInterface
{
    public function findByTenantId(string $tenantId): ?Subscription
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE tenant_id = :tid');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? Subscription::fromArray($row) : null;
    }

    public function findByStripeSubscriptionId(string $stripeId): ?Subscription
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM subscriptions WHERE stripe_subscription_id = :sid'
        );
        $stmt->execute(['sid' => $stripeId]);
        $row = $stmt->fetch();
        return $row !== false ? Subscription::fromArray($row) : null;
    }

    public function save(Subscription $sub): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscriptions
                (id, tenant_id, plan_id, stripe_customer_id, stripe_subscription_id,
                 status, billing_interval,
                 current_period_start, current_period_end,
                 trial_ends_at, cancelled_at, created_at, updated_at)
             VALUES
                (:id, :tenant_id, :plan_id, :stripe_customer_id, :stripe_subscription_id,
                 :status, :billing_interval,
                 :current_period_start, :current_period_end,
                 :trial_ends_at, :cancelled_at, NOW(), NOW())'
        );
        $stmt->execute($this->toParams($sub));
    }

    public function update(string $id, array $fields): void
    {
        $allowed = [
            'plan_id', 'stripe_customer_id', 'stripe_subscription_id',
            'status', 'billing_interval',
            'current_period_start', 'current_period_end',
            'trial_ends_at', 'cancelled_at',
        ];
        $set = [];
        $params = ['id' => $id];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $set[] = "$key = :$key";
            $params[$key] = $value instanceof \DateTimeImmutable
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }
        if (empty($set)) {
            return;
        }
        $set[] = 'updated_at = NOW()';
        $this->pdo->prepare('UPDATE subscriptions SET ' . implode(', ', $set) . ' WHERE id = :id')
                  ->execute($params);
    }

    public function findByTenantIds(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM subscriptions WHERE tenant_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($tenantIds));
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $sub = Subscription::fromArray($row);
            $result[$sub->getTenantId()] = $sub;
        }
        return $result;
    }

    public function getMetrics(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                                                          AS total,
                COUNT(*) FILTER (WHERE status = 'active')                         AS active,
                COUNT(*) FILTER (WHERE status = 'trial')                          AS trial,
                COUNT(*) FILTER (WHERE status = 'past_due')                       AS past_due,
                COALESCE(SUM(p.price_monthly_cents)
                    FILTER (WHERE s.status = 'active' AND s.billing_interval = 'monthly'), 0)
                + COALESCE(SUM(ROUND(p.price_yearly_cents / 12.0))
                    FILTER (WHERE s.status = 'active' AND s.billing_interval = 'yearly'), 0)
                                                                                  AS mrr_cents
             FROM subscriptions s
             LEFT JOIN plans p ON p.id = s.plan_id"
        )->fetch();

        return [
            'total'     => (int) $row['total'],
            'active'    => (int) $row['active'],
            'trial'     => (int) $row['trial'],
            'past_due'  => (int) $row['past_due'],
            'mrr_cents' => (int) $row['mrr_cents'],
        ];
    }

    private function toParams(Subscription $sub): array
    {
        $fmt = fn(?\DateTimeImmutable $d) => $d?->format('Y-m-d H:i:s');
        return [
            'id'                    => $sub->getId(),
            'tenant_id'             => $sub->getTenantId(),
            'plan_id'               => $sub->getPlanId(),
            'stripe_customer_id'    => $sub->getStripeCustomerId(),
            'stripe_subscription_id'=> $sub->getStripeSubscriptionId(),
            'status'                => $sub->getStatus(),
            'billing_interval'      => $sub->getBillingInterval(),
            'current_period_start'  => $fmt($sub->getCurrentPeriodStart()),
            'current_period_end'    => $fmt($sub->getCurrentPeriodEnd()),
            'trial_ends_at'         => $fmt($sub->getTrialEndsAt()),
            'cancelled_at'          => $fmt($sub->getCancelledAt()),
        ];
    }
}
