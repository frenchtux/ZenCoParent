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

    /** GET /admin/families/{id} */
    public function getFamily(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        return ApiResponse::success($response, $this->adminService->getFamilyDetail($args['id']));
    }

    /** PATCH /admin/families/{id}/modules */
    public function updateModules(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $body    = (array) $request->getParsedBody();
        $modules = $body['modules'] ?? null;

        if ($modules !== null && !is_array($modules)) {
            return ApiResponse::error($response, "modules doit être un objet ou null.", 400);
        }

        $this->adminService->setModulesOverride($args['id'], $modules);
        return ApiResponse::success($response, ['updated' => true]);
    }

    /** GET /admin/plans */
    public function listPlans(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        return ApiResponse::success($response, $this->adminService->listPlans());
    }

    /** PUT /admin/plans/{id} */
    public function updatePlan(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $body = (array) $request->getParsedBody();
        $plan = $this->adminService->updatePlan($args['id'], $body);
        return ApiResponse::success($response, $plan);
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

    /** GET /admin/payments */
    public function listPayments(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $params = $request->getQueryParams();
        $limit  = min((int) ($params['limit'] ?? 100), 500);
        $offset = max((int) ($params['offset'] ?? 0), 0);

        return ApiResponse::success($response, $this->adminService->listPayments($limit, $offset));
    }
}
