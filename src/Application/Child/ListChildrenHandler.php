<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Child;

use ZenCoParent\Domain\Child\ChildRepositoryInterface;

final class ListChildrenHandler
{
    public function __construct(
        private ChildRepositoryInterface $childRepo,
    ) {}

    /**
     * @return ChildDTO[]
     */
    public function handle(string $tenantId): array
    {
        $children = $this->childRepo->findByTenantId($tenantId);
        return array_map(fn($c) => ChildDTO::fromChild($c), $children);
    }
}
