<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ZenCoParent\Application\License\LicenseService;

final class RequireLicenseMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Community mode: no licensing
        if (($_ENV['APP_MODE'] ?? 'saas') !== 'saas') {
            return $handler->handle($request);
        }

        $license = $this->licenseService->getOrCreate();

        if ($license->isLicensed()) {
            // Forward license info as a request attribute for controllers that need it
            return $handler->handle(
                $request->withAttribute('license', $license)
            );
        }

        // Trial expired and not activated: 402 Payment Required
        $response = new \Slim\Psr7\Response();
        $body     = json_encode([
            'success' => false,
            'error'   => 'license_expired',
            'message' => "La période d'essai de 30 jours a expiré. Activez votre licence sur /frontend/license.html",
            'data'    => [
                'installation_key'     => $license->getInstallationKey(),
                'trial_days_remaining' => 0,
                'is_active'            => false,
            ],
        ]);
        $response->getBody()->write((string) $body);
        return $response
            ->withStatus(402)
            ->withHeader('Content-Type', 'application/json');
    }
}
