<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\License\LicenseService;

final class AdminLicenseController
{
    public function __construct(private LicenseService $licenseService) {}

    /** GET /admin/license/status — full license state (master-key protected) */
    public function status(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $license = $this->licenseService->getOrCreate();

        $data = array_merge($license->toArray(), [
            'instance_id'          => $license->getInstanceId(),
            'fingerprint_recorded' => $license->getMachineFingerprint() !== null,
        ]);

        return ApiResponse::success($response, $data);
    }

    /** POST /admin/license/revoke — revoke the installation license (master-key protected) */
    public function revoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ok = $this->licenseService->revoke();

        if (!$ok) {
            return ApiResponse::error($response, 'No active license found or license is already revoked.', 409);
        }

        return ApiResponse::success($response, ['message' => 'License revoked successfully.']);
    }
}
