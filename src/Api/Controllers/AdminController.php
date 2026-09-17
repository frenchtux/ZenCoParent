<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Admin\AdminService;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\UserTenantAccessRepositoryInterface;

final class AdminController
{
    public function __construct(
        private readonly AdminService                      $adminService,
        private readonly UserRepositoryInterface           $userRepo,
        private readonly TenantRepositoryInterface         $tenantRepo,
        private readonly UserTenantAccessRepositoryInterface $utaRepo,
    ) {}

    /** GET /admin/dashboard */
    public function dashboard(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        return ApiResponse::success($response, $this->adminService->getMetrics());
    }

    /** GET /admin/families */
    public function listFamilies(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $params = $request->getQueryParams();
        $limit  = min((int) ($params['limit'] ?? 50), 200);
        $offset = max((int) ($params['offset'] ?? 0), 0);

        return ApiResponse::success($response, $this->adminService->listFamilies($limit, $offset));
    }

    // ─── User → Tenant assignment ────────────────────────────────────────────

    /** GET /admin/users/{id}/tenants — list tenants accessible by a user */
    public function getUserTenants(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $user = $this->userRepo->findById($args['id']);
        if ($user === null) {
            return ApiResponse::error($response, 'User not found.', 404);
        }
        $tenants = $this->utaRepo->findTenantsByUserId($args['id']);
        return ApiResponse::success($response, $tenants);
    }

    /** PUT /admin/users/{id}/tenants — replace tenant list for a user */
    public function setUserTenants(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $user = $this->userRepo->findById($args['id']);
        if ($user === null) {
            return ApiResponse::error($response, 'User not found.', 404);
        }

        $body      = (array) $request->getParsedBody();
        $tenantIds = $body['tenant_ids'] ?? [];
        $role      = (string) ($body['role'] ?? $user->getRole()->value);

        if (!is_array($tenantIds)) {
            return ApiResponse::error($response, 'tenant_ids doit être un tableau.', 400);
        }

        // Validate each tenant exists
        foreach ($tenantIds as $tid) {
            if ($this->tenantRepo->findById((string) $tid) === null) {
                return ApiResponse::error($response, "Tenant introuvable : {$tid}", 404);
            }
        }

        $this->utaRepo->setTenants($args['id'], $tenantIds, $role);
        $tenants = $this->utaRepo->findTenantsByUserId($args['id']);
        return ApiResponse::success($response, $tenants);
    }

}
