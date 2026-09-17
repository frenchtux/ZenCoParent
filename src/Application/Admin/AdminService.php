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
        return [
            'families' => [
                'total' => $this->tenantRepo->countAll(),
            ],
        ];
    }

    /** Paginated list of families */
    public function listFamilies(int $limit = 50, int $offset = 0): array
    {
        return array_map(
            fn($tenant) => $tenant->toArray(),
            $this->tenantRepo->findAll($limit, $offset),
        );
    }

}
