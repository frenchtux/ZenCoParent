<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\License\LicenseService;

final class LicenseController
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    /** GET /license — current license status (no auth required in SaaS) */
    public function status(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $license = $this->licenseService->getOrCreate();
        return ApiResponse::success($response, $license->toArray());
    }

    /** POST /license/activate — submit activation key */
    public function activate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body           = (array) $request->getParsedBody();
        $activationKey  = trim((string) ($body['activation_key'] ?? ''));

        if ($activationKey === '') {
            return ApiResponse::error($response, "La clé d'activation est requise.", 400);
        }

        $ok = $this->licenseService->activate($activationKey);

        if (!$ok) {
            return ApiResponse::error($response, "Clé d'activation invalide.", 422);
        }

        $license = $this->licenseService->getOrCreate();
        return ApiResponse::success($response, $license->toArray());
    }
}
