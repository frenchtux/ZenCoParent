<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Admin\AdminService;

final class AdminController
{
    public function __construct(
        private readonly AdminService $adminService,
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
