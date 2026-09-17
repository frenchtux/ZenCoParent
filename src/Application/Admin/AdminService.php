<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Admin;

use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;

final class AdminService
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepo,
    ) {}

    /** Summary metrics for the admin dashboard */
    public function getMetrics(): array
    {
        $totalTenants = $this->tenantRepo->countAll();

        return [
            'families'  => [
                'total'    => $totalTenants,
                'active'   => $totalTenants,
                'trial'    => 0,
                'past_due' => 0,
                'mrr_cents' => 0,
            ],
            'plans'     => [],
            'mrr_euros' => 0.0,
        ];
    }

    /** Paginated list of families */
    public function listFamilies(int $limit = 50, int $offset = 0): array
    {
        $tenants = $this->tenantRepo->findAll($limit, $offset);
        if (empty($tenants)) {
            return [];
        }

        return array_map(function ($tenant) {
            $row = $tenant->toArray();
            $row['subscription'] = null;
            $row['plan']         = null;
            return $row;
        }, $tenants);
    }

    /** Full detail of a single family */
    public function getFamilyDetail(string $tenantId): array
    {
        $tenant = $this->tenantRepo->findById($tenantId);
        if ($tenant === null) {
            throw new \ZenCoParent\Domain\Shared\Exception\NotFoundException('Family not found');
        }

        return [
            'tenant'       => $tenant->toArray(),
            'subscription' => null,
            'plan'         => null,
            'payments'     => [],
        ];
    }

    /** Admin override: set per-tenant module flags */
    public function setModulesOverride(string $tenantId, ?array $modules): void
    {
        $this->tenantRepo->updateModulesOverride($tenantId, $modules);
    }
}
