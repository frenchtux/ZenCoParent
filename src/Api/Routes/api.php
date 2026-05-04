<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use ZenCoParent\Api\Controllers\AuthController;
use ZenCoParent\Api\Controllers\ChildController;
use ZenCoParent\Api\Controllers\EventController;
use ZenCoParent\Api\Controllers\MedicalRecordController;
use ZenCoParent\Api\Controllers\UserController;
use ZenCoParent\Api\Middleware\AuthMiddleware;
use ZenCoParent\Api\Middleware\CsrfMiddleware;
use ZenCoParent\Api\Middleware\RateLimitMiddleware;
use ZenCoParent\Api\Middleware\RequireRoleMiddleware;
use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Infrastructure\Cache\RedisRateLimiter;

return function (App $app): void {
    $container = $app->getContainer();

    // ── Global middleware (applied to every route, outermost = last added) ──
    $app->add(new CsrfMiddleware());
    $app->add(new RateLimitMiddleware($container->get(RedisRateLimiter::class)));

    // ── Auth routes (no JWT required) ────────────────────────────────────────
    $app->group('/auth', function (RouteCollectorProxy $group) use ($container): void {
        $group->post('/login',   [AuthController::class, 'login']);
        $group->post('/refresh', [AuthController::class, 'refresh']);

        // Logout requires a valid JWT
        $group->post('/logout', [AuthController::class, 'logout'])
              ->add(new AuthMiddleware($container->get(JWTService::class)));

        // Google OAuth flow
        $group->get('/oauth/google/{tenantSlug}',           [AuthController::class, 'oauthRedirect']);
        $group->get('/oauth/google/{tenantSlug}/callback',  [AuthController::class, 'oauthCallback']);
    });

    // ── Protected routes ─────────────────────────────────────────────────────
    $authMiddleware = new AuthMiddleware($container->get(JWTService::class));

    // Users — GET open to all authenticated; POST restricted to admins
    $app->group('/users', function (RouteCollectorProxy $group): void {
        $group->get('',  [UserController::class, 'index']);
        $group->post('', [UserController::class, 'create'])
              ->add(new RequireRoleMiddleware(['admin']));
    })->add($authMiddleware);

    // Children — all authenticated parents/admins
    $app->group('/children', function (RouteCollectorProxy $group): void {
        $group->get('',  [ChildController::class, 'index']);
        $group->post('', [ChildController::class, 'create']);
        // Medical history nested under /children/{id}/medical-history
        $group->get('/{id}/medical-history', [MedicalRecordController::class, 'childHistory']);
    })->add($authMiddleware);

    // Events — full CRUD for authenticated users
    $app->group('/events', function (RouteCollectorProxy $group): void {
        $group->get('',         [EventController::class, 'index']);
        $group->post('',        [EventController::class, 'create']);
        $group->get('/{id}',    [EventController::class, 'show']);
        $group->put('/{id}',    [EventController::class, 'update']);
        $group->delete('/{id}', [EventController::class, 'destroy']);
    })->add($authMiddleware);

    // Medical records — standalone creation (not linked to an event)
    $app->post('/medical-records', [MedicalRecordController::class, 'create'])
        ->add($authMiddleware);
};
