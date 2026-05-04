<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use ZenCoParent\Domain\Auth\OAuthAccountRepositoryInterface;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Shared\TransactionManagerInterface;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Infrastructure\Auth\GoogleOAuthService;
use ZenCoParent\Infrastructure\Auth\JWTService;
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
            return new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLRefreshTokenRepository($pdo);
        },

        OAuthAccountRepositoryInterface::class => function (ContainerInterface $c) {
            $pdo = $c->get(\PDO::class);
            return new \ZenCoParent\Infrastructure\Persistence\PostgreSQL\PostgreSQLOAuthAccountRepository($pdo);
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

        TransactionManagerInterface::class => function (ContainerInterface $c) {
            return new PDOTransactionManager($c->get(\PDO::class));
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

        // Rate Limiter
        RedisRateLimiter::class => function (ContainerInterface $c) {
            $authConfig = require __DIR__ . '/../Config/auth.php';
            return new RedisRateLimiter(
                $c->get(\Predis\Client::class),
                $authConfig['rate_limit']['requests'],
                $authConfig['rate_limit']['window'],
            );
        },

    ]);
};
