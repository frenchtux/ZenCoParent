<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Admin;

use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;

final class AdminService
{
    public function __construct(
        private readonly TenantRepositoryInterface       $tenantRepo,
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly PlanRepositoryInterface         $planRepo,
        private readonly PaymentRepositoryInterface      $paymentRepo,
    ) {}

    /** Summary metrics for the admin dashboard */
    public function getMetrics(): array
    {
        $subMetrics = $this->subscriptionRepo->getMetrics();
        $plans = $this->planRepo->findAll();

        return [
            'families'  => $subMetrics,
            'plans'     => array_map(fn($p) => $p->toArray(), $plans),
            'mrr_euros' => round($subMetrics['mrr_cents'] / 100, 2),
        ];
    }

    /** Paginated list of families enriched with their subscription and plan */
    public function listFamilies(int $limit = 50, int $offset = 0): array
    {
        $tenants = $this->tenantRepo->findAll($limit, $offset);
        $result  = [];

        foreach ($tenants as $tenant) {
            $sub  = $this->subscriptionRepo->findByTenantId($tenant->getId());
            $plan = ($sub?->getPlanId()) ? $this->planRepo->findById($sub->getPlanId()) : null;

            $row              = $tenant->toArray();
            $row['subscription'] = $sub?->toArray();
            $row['plan']         = $plan?->toArray();
            $result[]            = $row;
        }

        return $result;
    }

    /** Full detail of a single family */
    public function getFamilyDetail(string $tenantId): array
    {
        $tenant = $this->tenantRepo->findById($tenantId);
        if ($tenant === null) {
            throw new \ZenCoParent\Domain\Shared\Exception\NotFoundException('Family not found');
        }

        $sub      = $this->subscriptionRepo->findByTenantId($tenantId);
        $plan     = ($sub?->getPlanId()) ? $this->planRepo->findById($sub->getPlanId()) : null;
        $payments = $this->paymentRepo->findByTenantId($tenantId, 20);

        return [
            'tenant'    => $tenant->toArray(),
            'subscription' => $sub?->toArray(),
            'plan'      => $plan?->toArray(),
            'payments'  => array_map(fn($p) => $p->toArray(), $payments),
        ];
    }

    /** Admin override: set per-tenant module flags (null clears and reverts to plan) */
    public function setModulesOverride(string $tenantId, ?array $modules): void
    {
        $this->tenantRepo->updateModulesOverride($tenantId, $modules);
    }

    /** Payment history (all tenants, paginated) */
    public function listPayments(int $limit = 100, int $offset = 0): array
    {
        return array_map(
            fn($p) => $p->toArray(),
            $this->paymentRepo->findAll($limit, $offset),
        );
    }

    // ── Plan management ──────────────────────────────────────────────────────

    public function listPlans(): array
    {
        return array_map(fn($p) => $p->toArray(), $this->planRepo->findAll());
    }

    public function updatePlan(string $planId, array $fields): array
    {
        $plan = $this->planRepo->findById($planId);
        if ($plan === null) {
            throw new \ZenCoParent\Domain\Shared\Exception\NotFoundException('Plan not found');
        }
        $this->planRepo->update($planId, $fields);
        return $this->planRepo->findById($planId)->toArray();
    }
}
