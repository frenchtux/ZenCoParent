<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ZenCoParent\Application\Admin\AdminService;
use ZenCoParent\Application\License\LicenseService;
use ZenCoParent\Application\Payment\StripeWebhookHandler;
use ZenCoParent\Application\Subscription\SubscriptionService;
use ZenCoParent\Application\User\DeleteAccountHandler;
use ZenCoParent\Application\User\GdprExportHandler;
use ZenCoParent\Domain\Auth\OAuthAccountRepositoryInterface;
use ZenCoParent\Domain\License\LicenseRepositoryInterface;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Notification\MailerInterface;
use ZenCoParent\Domain\Invitation\InvitationRepositoryInterface;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Domain\Photo\PhotoRepositoryInterface;
use ZenCoParent\Domain\Shared\TransactionManagerInterface;
use ZenCoParent\Domain\Storage\FileStorageInterface;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Application\Auth\RegisterHandler;
use ZenCoParent\Application\Invitation\AcceptInvitationHandler;
use ZenCoParent\Application\Invitation\CreateInvitationHandler;
use ZenCoParent\Application\Invitation\GetInvitationHandler;
use ZenCoParent\Application\User\ChangeCredentialsHandler;
use ZenCoParent\Application\User\ChangePasswordHandler;
use ZenCoParent\Application\Settings\TenantSettingsService;
use ZenCoParent\Domain\User\UserTenantAccessRepositoryInterface;
use ZenCoParent\Application\User\GetUserHandler;
use ZenCoParent\Application\User\UpdateUserHandler;
use ZenCoParent\Infrastructure\Auth\GoogleOAuthService;
use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Infrastructure\Cache\NullRateLimiter;
use ZenCoParent\Infrastructure\Cache\RedisRateLimiter;
use ZenCoParent\Infrastructure\Database\Connection;
use ZenCoParent\Infrastructure\Persistence\PDOTransactionManager;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([

        // PDO
        \PDO::class => fn() => Connection::getInstance(),

        // Repos — bind interface to concrete based on APP_MODE
        UserRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteUserRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLUserRepository($pdo);
        },

        ChildRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteChildRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLChildRepository($pdo);
        },

        TenantRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteTenantRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLTenantRepository($pdo);
        },

        RefreshTokenRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteRefreshTokenRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLRefreshTokenRepository($pdo);
        },

        OAuthAccountRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteOAuthAccountRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLOAuthAccountRepository($pdo);
        },

        EventRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteEventRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLEventRepository($pdo);
        },

        MedicalRecordRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteMedicalRecordRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLMedicalRecordRepository($pdo);
        },

        // File storage — MinIO for SaaS, local disk for Community
        FileStorageInterface::class => function () {
            if (($_ENV['APP_MODE'] ?? 'saas') === 'community') {
                $basePath = $_ENV['STORAGE_PATH'] ?? (dirname(__DIR__, 2) . '/storage');
                $baseUrl  = $_ENV['STORAGE_URL']  ?? '/storage';
                return new \ZenCoParent\Infrastructure\Storage\LocalStorageService($basePath, $baseUrl);
            }
            return new \ZenCoParent\Infrastructure\Storage\MinIOStorageService(
                endpoint:  $_ENV['MINIO_ENDPOINT']   ?? 'http://minio:9000',
                bucket:    $_ENV['MINIO_BUCKET']     ?? 'zencoparent',
                accessKey: $_ENV['MINIO_ACCESS_KEY'] ?? '',
                secretKey: $_ENV['MINIO_SECRET_KEY'] ?? '',
                region:    $_ENV['MINIO_REGION']     ?? 'us-east-1',
            );
        },

        PhotoRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLitePhotoRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLPhotoRepository($pdo);
        },

        InvitationRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteInvitationRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLInvitationRepository($pdo);
        },

        ExpenseRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteExpenseRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLExpenseRepository($pdo);
        },

        ThreadRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteThreadRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLThreadRepository($pdo);
        },

        MessageRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteMessageRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLMessageRepository($pdo);
        },

        TransactionManagerInterface::class => function (ContainerInterface $c) {
            return new PDOTransactionManager($c->get(\PDO::class));
        },

        // Auth handlers
        RegisterHandler::class => function (ContainerInterface $c) {
            return new RegisterHandler(
                $c->get(TenantRepositoryInterface::class),
                $c->get(UserRepositoryInterface::class),
                $c->get(RefreshTokenRepositoryInterface::class),
                $c->get(JWTService::class),
                $c->get(MailerInterface::class),
                $c->get(LoggerInterface::class),
            );
        },

        // Invitation handlers
        CreateInvitationHandler::class => function (ContainerInterface $c) {
            return new CreateInvitationHandler(
                $c->get(InvitationRepositoryInterface::class),
                $c->get(TenantRepositoryInterface::class),
                $c->get(UserRepositoryInterface::class),
                $c->get(MailerInterface::class),
                $c->get(LoggerInterface::class),
                rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
            );
        },

        GetInvitationHandler::class => function (ContainerInterface $c) {
            return new GetInvitationHandler(
                $c->get(InvitationRepositoryInterface::class),
                $c->get(TenantRepositoryInterface::class),
            );
        },

        AcceptInvitationHandler::class => function (ContainerInterface $c) {
            return new AcceptInvitationHandler(
                $c->get(InvitationRepositoryInterface::class),
                $c->get(UserRepositoryInterface::class),
                $c->get(RefreshTokenRepositoryInterface::class),
                $c->get(JWTService::class),
            );
        },

        // Invitation controller
        \ZenCoParent\Api\Controllers\InvitationController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\InvitationController(
                $c->get(CreateInvitationHandler::class),
                $c->get(GetInvitationHandler::class),
                $c->get(AcceptInvitationHandler::class),
                $c->get(InvitationRepositoryInterface::class),
            );
        },

        // User handlers
        GetUserHandler::class => function (ContainerInterface $c) {
            return new GetUserHandler($c->get(UserRepositoryInterface::class));
        },

        UpdateUserHandler::class => function (ContainerInterface $c) {
            return new UpdateUserHandler($c->get(UserRepositoryInterface::class));
        },

        ChangePasswordHandler::class => function (ContainerInterface $c) {
            return new ChangePasswordHandler($c->get(UserRepositoryInterface::class));
        },

        ChangeCredentialsHandler::class => function (ContainerInterface $c) {
            return new ChangeCredentialsHandler($c->get(UserRepositoryInterface::class));
        },

        TenantSettingsService::class => function (ContainerInterface $c) {
            return new TenantSettingsService(
                $c->get(\PDO::class),
                $_ENV['APP_SECRET'] ?? 'changeme',
            );
        },

        \ZenCoParent\Api\Controllers\SettingsController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\SettingsController(
                $c->get(TenantSettingsService::class),
            );
        },

        UserTenantAccessRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return ($_ENV['APP_MODE'] ?? 'saas') === 'community'
                ? new \ZenCoParent\Infrastructure\Persistence\SQLite\SQLiteUserTenantAccessRepository($pdo)
                : new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLUserTenantAccessRepository($pdo);
        },

        // JWT Service
        JWTService::class => function () {
            $authConfig = require __DIR__ . '/../Config/auth.php';
            return new JWTService($authConfig['jwt_secret'], $authConfig['jwt_expiry']);
        },

        // Google OAuth
        GoogleOAuthService::class => function () {
            $authConfig = require __DIR__ . '/../Config/auth.php';
            return new GoogleOAuthService(
                $authConfig['google']['client_id'],
                $authConfig['google']['client_secret'],
                $authConfig['google']['redirect_uri'],
            );
        },

        // Redis
        \Predis\Client::class => function () {
            $redisConfig = require __DIR__ . '/../Config/redis.php';
            return new \Predis\Client([
                'scheme'   => 'tcp',
                'host'     => $redisConfig['host'],
                'port'     => $redisConfig['port'],
                'password' => $redisConfig['password'],
                'database' => $redisConfig['database'],
            ]);
        },

        // License repository + service (SaaS only)
        LicenseRepositoryInterface::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLLicenseRepository(
                $c->get(\PDO::class)
            );
        },

        LicenseService::class => function (ContainerInterface $c) {
            $masterKey = $_ENV['LICENSE_MASTER_KEY'] ?? '';
            if ($masterKey === '') {
                throw new \RuntimeException('LICENSE_MASTER_KEY must be set in the environment — no default is allowed.');
            }
            return new LicenseService(
                $c->get(LicenseRepositoryInterface::class),
                $masterKey,
                $c->get(LoggerInterface::class),
            );
        },

        \ZenCoParent\Api\Controllers\LicenseController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\LicenseController(
                $c->get(LicenseService::class)
            );
        },

        // ── Plan / Subscription / Payment repositories (SaaS only) ─────────────
        PlanRepositoryInterface::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLPlanRepository(
                $c->get(\PDO::class)
            );
        },

        SubscriptionRepositoryInterface::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLSubscriptionRepository(
                $c->get(\PDO::class)
            );
        },

        PaymentRepositoryInterface::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLPaymentRepository(
                $c->get(\PDO::class)
            );
        },

        // ── Application services ─────────────────────────────────────────────
        SubscriptionService::class => function (ContainerInterface $c) {
            return new SubscriptionService(
                $c->get(SubscriptionRepositoryInterface::class),
                $c->get(PlanRepositoryInterface::class),
                $c->get(TenantRepositoryInterface::class),
            );
        },

        AdminService::class => function (ContainerInterface $c) {
            return new AdminService(
                $c->get(TenantRepositoryInterface::class),
                $c->get(SubscriptionRepositoryInterface::class),
                $c->get(PlanRepositoryInterface::class),
                $c->get(PaymentRepositoryInterface::class),
            );
        },

        \ZenCoParent\Infrastructure\Payment\StripeService::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Infrastructure\Payment\StripeService(
                secretKey:              $_ENV['STRIPE_SECRET_KEY'] ?? '',
                webhookSecret:          $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
                installationKeyPriceId: $_ENV['STRIPE_INSTALLATION_KEY_PRICE_ID'] ?? '',
                appUrl:                 rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
                paymentRepo:            $c->get(PaymentRepositoryInterface::class),
            );
        },

        // ── Payment + Admin controllers ──────────────────────────────────────
        \ZenCoParent\Api\Controllers\PaymentController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\PaymentController(
                $c->get(\ZenCoParent\Infrastructure\Payment\StripeService::class),
                $c->get(PlanRepositoryInterface::class),
                $c->get(SubscriptionRepositoryInterface::class),
                $c->get(StripeWebhookHandler::class),
            );
        },

        \ZenCoParent\Api\Controllers\AdminController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\AdminController(
                $c->get(AdminService::class),
                $c->get(UserRepositoryInterface::class),
                $c->get(TenantRepositoryInterface::class),
                $c->get(UserTenantAccessRepositoryInterface::class),
            );
        },

        // ── Logger ───────────────────────────────────────────────────────────
        LoggerInterface::class => function () {
            $logger  = new \Monolog\Logger('zencoparent');
            $level   = ($_ENV['APP_DEBUG'] ?? 'false') === 'true'
                ? \Monolog\Level::Debug
                : \Monolog\Level::Info;
            $logDir  = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $file = new \Monolog\Handler\StreamHandler($logDir . '/app.log', $level);
            $file->setFormatter(new \Monolog\Formatter\JsonFormatter());
            $logger->pushHandler($file);
            $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stderr', \Monolog\Level::Warning));
            return $logger;
        },

        // ── Mailer ───────────────────────────────────────────────────────────
        MailerInterface::class => function () {
            $host = $_ENV['MAIL_HOST'] ?? '';
            if ($host === '' || ($_ENV['APP_MODE'] ?? 'saas') === 'community') {
                return new \ZenCoParent\Infrastructure\Notification\NullMailer();
            }
            return new \ZenCoParent\Infrastructure\Notification\SmtpMailer(
                host:        $host,
                port:        (int) ($_ENV['MAIL_PORT']        ?? 587),
                username:    $_ENV['MAIL_USERNAME']    ?? '',
                password:    $_ENV['MAIL_PASSWORD']    ?? '',
                encryption:  $_ENV['MAIL_ENCRYPTION']  ?? 'tls',
                fromAddress: $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@zencoparent.com',
                fromName:    $_ENV['MAIL_FROM_NAME']    ?? 'ZenCoParent',
            );
        },

        // ── Stripe webhook handler ────────────────────────────────────────────
        StripeWebhookHandler::class => function (ContainerInterface $c) {
            return new StripeWebhookHandler(
                $c->get(PaymentRepositoryInterface::class),
                $c->get(SubscriptionRepositoryInterface::class),
                $c->get(PlanRepositoryInterface::class),
                $c->get(SubscriptionService::class),
                $c->get(UserRepositoryInterface::class),
                $c->get(MailerInterface::class),
                $c->get(LoggerInterface::class),
            );
        },

        // ── GDPR export + account deletion ───────────────────────────────────
        GdprExportHandler::class => function (ContainerInterface $c) {
            return new GdprExportHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(ChildRepositoryInterface::class),
                $c->get(EventRepositoryInterface::class),
                $c->get(ExpenseRepositoryInterface::class),
                $c->get(\ZenCoParent\Domain\Photo\PhotoRepositoryInterface::class),
                $c->get(\ZenCoParent\Domain\Messaging\ThreadRepositoryInterface::class),
                $c->get(\ZenCoParent\Domain\Messaging\MessageRepositoryInterface::class),
                $c->get(MedicalRecordRepositoryInterface::class),
            );
        },

        DeleteAccountHandler::class => function (ContainerInterface $c) {
            return new DeleteAccountHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(TenantRepositoryInterface::class),
                $c->get(SubscriptionRepositoryInterface::class),
                $c->get(RefreshTokenRepositoryInterface::class),
                $c->get(SubscriptionService::class),
            );
        },

        // ── Notification + Account controllers ───────────────────────────────
        \ZenCoParent\Api\Controllers\NotificationController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\NotificationController(
                $c->get(\ZenCoParent\Domain\Messaging\MessageRepositoryInterface::class),
            );
        },

        \ZenCoParent\Api\Controllers\AccountController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\AccountController(
                $c->get(GdprExportHandler::class),
                $c->get(DeleteAccountHandler::class),
            );
        },

        \ZenCoParent\Api\Controllers\AdminLicenseController::class => function (ContainerInterface $c) {
            return new \ZenCoParent\Api\Controllers\AdminLicenseController(
                $c->get(LicenseService::class)
            );
        },

        // Rate Limiter — NullRateLimiter in community mode (no Redis)
        RedisRateLimiter::class => function (ContainerInterface $c) {
            if (($_ENV['APP_MODE'] ?? 'saas') === 'community') {
                return new NullRateLimiter();
            }
            $authConfig = require __DIR__ . '/../Config/auth.php';
            return new RedisRateLimiter(
                $c->get(\Predis\Client::class),
                $authConfig['rate_limit']['requests'],
                $authConfig['rate_limit']['window'],
            );
        },

    ]);
};
