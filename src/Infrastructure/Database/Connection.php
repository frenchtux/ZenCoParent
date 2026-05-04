<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Database;

final class Connection
{
    private static ?\PDO $instance = null;

    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            self::$instance = self::create();
        }
        return self::$instance;
    }

    private static function create(): \PDO
    {
        $mode = $_ENV['APP_MODE'] ?? 'saas';
        return $mode === 'community' ? self::createSQLite() : self::createPostgres();
    }

    private static function createPostgres(): \PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
            $_ENV['DB_HOST'] ?? 'postgres',
            $_ENV['DB_PORT'] ?? '5432',
            $_ENV['DB_DATABASE'] ?? 'zencoparent'
        );
        $pdo = new \PDO($dsn, $_ENV['DB_USERNAME'] ?? '', $_ENV['DB_PASSWORD'] ?? '', [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET TIME ZONE 'UTC'");
        return $pdo;
    }

    private static function createSQLite(): \PDO
    {
        $file = $_ENV['DB_FILE'] ?? dirname(__DIR__, 3) . '/storage/database.sqlite';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new \PDO("sqlite:{$file}", options: [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
