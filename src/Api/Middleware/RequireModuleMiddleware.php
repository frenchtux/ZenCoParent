<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ZenCoParent\Application\Subscription\SubscriptionService;

final class RequireModuleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly string              $module,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Community mode: all modules always available
        if (($_ENV['APP_MODE'] ?? 'saas') !== 'saas') {
            return $handler->handle($request);
        }

        $tenantId = $request->getAttribute('tenantId');
        if ($tenantId && $this->subscriptionService->isModuleEnabled($tenantId, $this->module)) {
            return $handler->handle($request);
        }

        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error'   => 'module_disabled',
            'message' => "Le module « {$this->module} » n'est pas inclus dans votre abonnement.",
        ]));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }
}
