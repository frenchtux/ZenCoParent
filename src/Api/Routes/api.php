<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use ZenCoParent\Api\Controllers\AccountController;
use ZenCoParent\Api\Controllers\AdminController;
use ZenCoParent\Api\Controllers\MedicalAttachmentController;
use ZenCoParent\Api\Controllers\SettingsController;
use ZenCoParent\Api\Controllers\AuthController;
use ZenCoParent\Api\Controllers\NotificationController;
use ZenCoParent\Api\Middleware\RequireMasterKeyMiddleware;
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

    // ── Public mode endpoint ─────────────────────────────────────────────────
    $app->get('/mode', function ($request, $response) {
        $data = ['mode' => $_ENV['APP_MODE'] ?? 'saas', 'version' => '1.0'];
        $response->getBody()->write(json_encode(['success' => true, 'data' => $data]));
        return $response->withHeader('Content-Type', 'application/json');
    });

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

        // Switch active tenant (requires JWT)
        $group->post('/switch-tenant', [AuthController::class, 'switchTenant'])
              ->add(new AuthMiddleware($container->get(JWTService::class)));
    });

    // ── Invitation public routes (no auth) ───────────────────────────────────
    $app->get('/invitations/{token}',         [InvitationController::class, 'show']);
    $app->post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // ── Protected routes ─────────────────────────────────────────────────────
    $authMiddleware = new AuthMiddleware($container->get(JWTService::class));

    $app->group('', function (RouteCollectorProxy $outer) use ($container): void {

        // ── Admin routes (role = admin) ──────────────────────────────────────
        $outer->group('/admin', function (RouteCollectorProxy $g) use ($container): void {
            $g->get('',                          [AdminController::class, 'dashboard']);
            $g->get('/dashboard',                [AdminController::class, 'dashboard']);
            $g->get('/families',                 [AdminController::class, 'listFamilies']);
            $g->get('/families/{id}',            [AdminController::class, 'getFamily']);
            $g->patch('/families/{id}/modules',  [AdminController::class, 'updateModules']);
            // User → Tenant assignment
            $g->get('/users/{id}/tenants',       [AdminController::class, 'getUserTenants']);
            $g->put('/users/{id}/tenants',       [AdminController::class, 'setUserTenants']);
            // Mail settings
            $g->get('/settings/mail',            [SettingsController::class, 'getMail']);
            $g->put('/settings/mail',            [SettingsController::class, 'putMail']);
            $g->post('/settings/mail/test',      [SettingsController::class, 'testMail']);
            // OAuth settings (system-level)
            $g->get('/settings/oauth',           [SettingsController::class, 'getOAuth']);
            $g->put('/settings/oauth',           [SettingsController::class, 'putOAuth']);
            // App settings (system-level)
            $g->get('/settings/app',             [SettingsController::class, 'getApp']);
            $g->put('/settings/app',             [SettingsController::class, 'putApp']);
            // Security settings (system-level)
            $g->get('/settings/security',        [SettingsController::class, 'getSecurity']);
            $g->put('/settings/security',        [SettingsController::class, 'putSecurity']);
        })->add(new RequireRoleMiddleware(['admin']));

        // Users — full management
        $outer->group('/users', function (RouteCollectorProxy $group): void {
            $group->get('',    [UserController::class, 'index']);
            $group->post('',   [UserController::class, 'create'])
                  ->add(new RequireRoleMiddleware(['admin']));
            $group->get('/me',                [UserController::class, 'me']);
            $group->patch('/me/credentials',  [UserController::class, 'changeCredentials']);
            $group->get('/{id}',              [UserController::class, 'show']);
            $group->put('/{id}',              [UserController::class, 'update']);
            $group->patch('/{id}/password',   [UserController::class, 'changePassword']);
        });

        // Children — base module (always available); medical sub-route gated
        $outer->group('/children', function (RouteCollectorProxy $group): void {
            $group->get('',      [ChildController::class, 'index']);
            $group->post('',     [ChildController::class, 'create']);
            $group->put('/{id}', [ChildController::class, 'update']);
            $group->get('/{id}/medical-history', [MedicalRecordController::class, 'childHistory']);
        });

        // Events — full CRUD (always available)
        $outer->group('/events', function (RouteCollectorProxy $group): void {
            $group->get('',         [EventController::class, 'index']);
            $group->post('',        [EventController::class, 'create']);
            $group->get('/{id}',    [EventController::class, 'show']);
            $group->put('/{id}',    [EventController::class, 'update']);
            $group->delete('/{id}', [EventController::class, 'destroy']);
        });

        // Medical records
        $outer->post('/medical-records', [MedicalRecordController::class, 'create']);
        $outer->delete('/medical-records/{id}', [MedicalRecordController::class, 'delete']);

        // Medical attachments
        $outer->group('/medical-records/{id}/attachments', function (RouteCollectorProxy $group): void {
            $group->get('',                            [MedicalAttachmentController::class, 'index']);
            $group->post('',                           [MedicalAttachmentController::class, 'upload']);
            $group->get('/{attachmentId}/download',    [MedicalAttachmentController::class, 'download']);
            $group->delete('/{attachmentId}',          [MedicalAttachmentController::class, 'delete']);
        });

        // Photos
        $outer->group('/photos', function (RouteCollectorProxy $group): void {
            $group->get('',         [PhotoController::class, 'index']);
            $group->post('',        [PhotoController::class, 'upload']);
            $group->delete('/{id}', [PhotoController::class, 'destroy']);
        });

        // Invitations — protected management
        $outer->group('/invitations', function (RouteCollectorProxy $group): void {
            $group->get('',  [InvitationController::class, 'list']);
            $group->post('', [InvitationController::class, 'create']);
        });

        // Expenses
        $outer->group('/expenses', function (RouteCollectorProxy $group): void {
            $group->get('',         [ExpenseController::class, 'index']);
            $group->post('',        [ExpenseController::class, 'create']);
            $group->put('/{id}',    [ExpenseController::class, 'update']);
            $group->delete('/{id}', [ExpenseController::class, 'destroy']);
        });

        // Threads + Messages
        $outer->group('/threads', function (RouteCollectorProxy $group): void {
            $group->get('',    [ThreadController::class, 'index']);
            $group->post('',   [ThreadController::class, 'create']);
            $group->get('/{id}',                              [ThreadController::class, 'show']);
            $group->get('/{id}/messages',                     [ThreadController::class, 'messages']);
            $group->post('/{id}/messages',                    [ThreadController::class, 'sendMessage']);
            $group->patch('/{id}/messages/{msgId}/read',      [ThreadController::class, 'markRead']);
        });

        // Notifications summary (unread count)
        $outer->get('/notifications/summary', [NotificationController::class, 'summary']);

        // Account: GDPR export + account deletion
        $outer->get('/account/export',  [AccountController::class, 'export']);
        $outer->delete('/account',      [AccountController::class, 'delete']);

    })->add($authMiddleware);
};
