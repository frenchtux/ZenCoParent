<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use ZenCoParent\Api\Controllers\AccountController;
use ZenCoParent\Api\Controllers\AdminController;
use ZenCoParent\Api\Controllers\SettingsController;
use ZenCoParent\Api\Controllers\AdminLicenseController;
use ZenCoParent\Api\Controllers\AuthController;
use ZenCoParent\Api\Controllers\LicenseController;
use ZenCoParent\Api\Controllers\NotificationController;
use ZenCoParent\Api\Controllers\PaymentController;
use ZenCoParent\Api\Middleware\RequireLicenseMiddleware;
use ZenCoParent\Api\Middleware\RequireMasterKeyMiddleware;
use ZenCoParent\Api\Middleware\RequireModuleMiddleware;
use ZenCoParent\Application\License\LicenseService;
use ZenCoParent\Application\Subscription\SubscriptionService;
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

    // ── License middleware (SaaS only — applied to all protected routes) ─────
    $licenseMiddleware = null;
    if (($_ENV['APP_MODE'] ?? 'saas') === 'saas') {
        $licenseMiddleware = new RequireLicenseMiddleware($container->get(LicenseService::class));
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

    // ── License routes (public — accessible even when trial expired) ─────────
    $app->get('/license',          [LicenseController::class, 'status']);
    $app->post('/license/activate',[LicenseController::class, 'activate']);

    // ── Admin license routes (master-key protected, no JWT required) ──────────
    if (($_ENV['APP_MODE'] ?? 'saas') === 'saas') {
        $masterKey = $_ENV['LICENSE_MASTER_KEY'] ?? '';
        $app->group('/admin/license', function (RouteCollectorProxy $group) use ($container): void {
            $group->get('/status',  [AdminLicenseController::class, 'status']);
            $group->post('/revoke', [AdminLicenseController::class, 'revoke']);
        })->add(new RequireMasterKeyMiddleware($masterKey));
    }

    // ── Payment routes ────────────────────────────────────────────────────────
    // Webhook is public (Stripe signature verified inside the handler)
    $app->post('/payments/webhook',                    [PaymentController::class, 'webhook']);
    // Installation key checkout: public (no account needed to buy a key)
    $app->post('/payments/checkout/installation-key',  [PaymentController::class, 'checkoutInstallationKey']);

    // ── Invitation public routes (no auth) ───────────────────────────────────
    $app->get('/invitations/{token}',         [InvitationController::class, 'show']);
    $app->post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // ── Protected routes ─────────────────────────────────────────────────────
    // All routes inside this outer group share: JWT auth + (SaaS) license gate.
    // Middleware execution order (LIFO): licenseMiddleware → authMiddleware → handler.
    $authMiddleware = new AuthMiddleware($container->get(JWTService::class));

    $subscriptionService = $container->get(SubscriptionService::class);
    $moduleMiddleware    = fn(string $module) => new RequireModuleMiddleware($subscriptionService, $module);

    $protectedGroup = $app->group('', function (RouteCollectorProxy $outer) use ($container, $moduleMiddleware): void {

        // ── Subscription checkout (authenticated) ────────────────────────────
        $outer->post('/payments/checkout/subscription', [PaymentController::class, 'checkoutSubscription']);
        $outer->get('/payments/portal',                 [PaymentController::class, 'portal']);

        // ── Admin routes (role = admin) ──────────────────────────────────────
        $outer->group('/admin', function (RouteCollectorProxy $g) use ($container): void {
            $g->get('',                          [AdminController::class, 'dashboard']);
            $g->get('/dashboard',                [AdminController::class, 'dashboard']);
            $g->get('/families',                 [AdminController::class, 'listFamilies']);
            $g->get('/families/{id}',            [AdminController::class, 'getFamily']);
            $g->patch('/families/{id}/modules',  [AdminController::class, 'updateModules']);
            $g->get('/plans',                    [AdminController::class, 'listPlans']);
            $g->put('/plans/{id}',               [AdminController::class, 'updatePlan']);
            $g->get('/payments',                 [AdminController::class, 'listPayments']);
            // User → Tenant assignment
            $g->get('/users/{id}/tenants',       [AdminController::class, 'getUserTenants']);
            $g->put('/users/{id}/tenants',       [AdminController::class, 'setUserTenants']);
            // Mail settings
            $g->get('/settings/mail',            [SettingsController::class, 'getMail']);
            $g->put('/settings/mail',            [SettingsController::class, 'putMail']);
            $g->post('/settings/mail/test',      [SettingsController::class, 'testMail']);
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
        $outer->group('/children', function (RouteCollectorProxy $group) use ($moduleMiddleware): void {
            $group->get('',      [ChildController::class, 'index']);
            $group->post('',     [ChildController::class, 'create']);
            $group->put('/{id}', [ChildController::class, 'update']);
            $group->get('/{id}/medical-history', [MedicalRecordController::class, 'childHistory'])
                  ->add($moduleMiddleware('medical'));
        });

        // Events — full CRUD (always available)
        $outer->group('/events', function (RouteCollectorProxy $group): void {
            $group->get('',         [EventController::class, 'index']);
            $group->post('',        [EventController::class, 'create']);
            $group->get('/{id}',    [EventController::class, 'show']);
            $group->put('/{id}',    [EventController::class, 'update']);
            $group->delete('/{id}', [EventController::class, 'destroy']);
        });

        // Medical records — standalone creation (module: medical)
        $outer->post('/medical-records', [MedicalRecordController::class, 'create'])
              ->add($moduleMiddleware('medical'));

        // Photos (module: photos)
        $outer->group('/photos', function (RouteCollectorProxy $group): void {
            $group->get('',         [PhotoController::class, 'index']);
            $group->post('',        [PhotoController::class, 'upload']);
            $group->delete('/{id}', [PhotoController::class, 'destroy']);
        })->add($moduleMiddleware('photos'));

        // Invitations — protected management
        $outer->group('/invitations', function (RouteCollectorProxy $group): void {
            $group->get('',  [InvitationController::class, 'list']);
            $group->post('', [InvitationController::class, 'create']);
        });

        // Expenses (module: expenses)
        $outer->group('/expenses', function (RouteCollectorProxy $group): void {
            $group->get('',         [ExpenseController::class, 'index']);
            $group->post('',        [ExpenseController::class, 'create']);
            $group->put('/{id}',    [ExpenseController::class, 'update']);
            $group->delete('/{id}', [ExpenseController::class, 'destroy']);
        })->add($moduleMiddleware('expenses'));

        // Threads + Messages (module: messages)
        $outer->group('/threads', function (RouteCollectorProxy $group): void {
            $group->get('',    [ThreadController::class, 'index']);
            $group->post('',   [ThreadController::class, 'create']);
            $group->get('/{id}',                              [ThreadController::class, 'show']);
            $group->get('/{id}/messages',                     [ThreadController::class, 'messages']);
            $group->post('/{id}/messages',                    [ThreadController::class, 'sendMessage']);
            $group->patch('/{id}/messages/{msgId}/read',      [ThreadController::class, 'markRead']);
        })->add($moduleMiddleware('messages'));

        // Notifications summary (unread count)
        $outer->get('/notifications/summary', [NotificationController::class, 'summary']);

        // Account: GDPR export + account deletion
        $outer->get('/account/export',  [AccountController::class, 'export']);
        $outer->delete('/account',      [AccountController::class, 'delete']);

    })->add($authMiddleware);

    // Apply license gate on top of auth (SaaS only; null in community mode)
    if ($licenseMiddleware !== null) {
        $protectedGroup->add($licenseMiddleware);
    }
};
