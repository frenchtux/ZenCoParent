<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Subscription;

use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Domain\Subscription\Subscription;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly PlanRepositoryInterface         $planRepo,
        private readonly TenantRepositoryInterface       $tenantRepo,
    ) {}

    public function getOrCreateForTenant(string $tenantId): Subscription
    {
        $sub = $this->subscriptionRepo->findByTenantId($tenantId);
        if ($sub === null) {
            $sub = Subscription::createTrial($tenantId);
            $this->subscriptionRepo->save($sub);
        }
        return $sub;
    }

    /**
     * Check whether a specific module is enabled for a tenant.
     * Admin overrides on the tenant take precedence over the plan.
     */
    public function isModuleEnabled(string $tenantId, string $module): bool
    {
        $tenant = $this->tenantRepo->findById($tenantId);
        if ($tenant === null) {
            return false;
        }

        // Admin override takes full precedence when set
        $override = $tenant->getModulesOverride();
        if ($override !== null) {
            return (bool) ($override[$module] ?? false);
        }

        $sub = $this->subscriptionRepo->findByTenantId($tenantId);
        if ($sub === null || !$sub->isActive()) {
            // No active subscription: only base modules (children + events) are available
            return false;
        }

        $plan = $sub->getPlanId() ? $this->planRepo->findById($sub->getPlanId()) : null;
        if ($plan === null) {
            return false;
        }

        return $plan->isModuleIncluded($module);
    }

    /**
     * Called by the Stripe webhook handler when a subscription is created or updated.
     */
    public function syncFromStripe(
        string  $stripeSubscriptionId,
        string  $stripeCustomerId,
        string  $tenantId,
        string  $planId,
        string  $status,
        string  $billingInterval,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): void {
        $sub = $this->subscriptionRepo->findByTenantId($tenantId);
        if ($sub === null) {
            $sub = Subscription::createTrial($tenantId);
            $this->subscriptionRepo->save($sub);
        }

        $this->subscriptionRepo->update($sub->getId(), [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_customer_id'     => $stripeCustomerId,
            'plan_id'                => $planId,
            'status'                 => $status,
            'billing_interval'       => $billingInterval,
            'current_period_start'   => $periodStart,
            'current_period_end'     => $periodEnd,
        ]);
    }

    public function cancel(string $tenantId): void
    {
        $sub = $this->subscriptionRepo->findByTenantId($tenantId);
        if ($sub === null) {
            return;
        }
        $this->subscriptionRepo->update($sub->getId(), [
            'status'       => Subscription::STATUS_CANCELLED,
            'cancelled_at' => new \DateTimeImmutable(),
        ]);
    }
}
