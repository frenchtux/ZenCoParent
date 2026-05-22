<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use ZenCoParent\Api\Controllers\AuthController;
use ZenCoParent\Api\Controllers\ChildController;
use ZenCoParent\Api\Controllers\EventController;
use ZenCoParent\Api\Controllers\ExpenseController;
use ZenCoParent\Api\Controllers\InvitationController;
use ZenCoParent\Api\Controllers\MedicalRecordController;
use ZenCoParent\Api\Controllers\PhotoController;
use ZenCoParent\Api\Controllers\ThreadController;
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
    // Rate limiting only in saas mode (community has no Redis)
    if (($_ENV['APP_MODE'] ?? 'saas') !== 'community') {
        $app->add(new RateLimitMiddleware($container->get(RedisRateLimiter::class)));
    }

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

        // Self-registration
        $group->post('/register', [AuthController::class, 'register']);
    });

    // ── Invitation public routes (no auth) ───────────────────────────────────
    $app->get('/invitations/{token}',         [InvitationController::class, 'show']);
    $app->post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // ── Protected routes ─────────────────────────────────────────────────────
    $authMiddleware = new AuthMiddleware($container->get(JWTService::class));

    // Users — full management
    $app->group('/users', function (RouteCollectorProxy $group): void {
        $group->get('',    [UserController::class, 'index']);
        $group->post('',   [UserController::class, 'create'])
              ->add(new RequireRoleMiddleware(['admin']));
        $group->get('/me', [UserController::class, 'me']);
        $group->get('/{id}',              [UserController::class, 'show']);
        $group->put('/{id}',              [UserController::class, 'update']);
        $group->patch('/{id}/password',   [UserController::class, 'changePassword']);
    })->add($authMiddleware);

    // Children — all authenticated parents/admins
    $app->group('/children', function (RouteCollectorProxy $group): void {
        $group->get('',         [ChildController::class, 'index']);
        $group->post('',        [ChildController::class, 'create']);
        $group->put('/{id}',    [ChildController::class, 'update']);
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

    // Photos — upload + list + delete (SaaS: MinIO, Community: local disk)
    $app->group('/photos', function (RouteCollectorProxy $group): void {
        $group->get('',         [PhotoController::class, 'index']);
        $group->post('',        [PhotoController::class, 'upload']);
        $group->delete('/{id}', [PhotoController::class, 'destroy']);
    })->add($authMiddleware);

    // Invitations — protected routes
    $app->group('/invitations', function (RouteCollectorProxy $group): void {
        $group->get('',  [InvitationController::class, 'list']);
        $group->post('', [InvitationController::class, 'create']);
    })->add($authMiddleware);

    // Expenses — full CRUD with split ratios
    $app->group('/expenses', function (RouteCollectorProxy $group): void {
        $group->get('',         [ExpenseController::class, 'index']);
        $group->post('',        [ExpenseController::class, 'create']);
        $group->put('/{id}',    [ExpenseController::class, 'update']);
        $group->delete('/{id}', [ExpenseController::class, 'destroy']);
    })->add($authMiddleware);

    // Threads + Messages — polling-based messaging
    $app->group('/threads', function (RouteCollectorProxy $group): void {
        $group->get('',    [ThreadController::class, 'index']);
        $group->post('',   [ThreadController::class, 'create']);
        $group->get('/{id}',  [ThreadController::class, 'show']);
        $group->get('/{id}/messages',  [ThreadController::class, 'messages']);
        $group->post('/{id}/messages', [ThreadController::class, 'sendMessage']);
        $group->patch('/{id}/messages/{msgId}/read', [ThreadController::class, 'markRead']);
    })->add($authMiddleware);
};
