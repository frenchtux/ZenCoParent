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

        $schema = $_ENV['DB_SCHEMA'] ?? '';
        if ($schema !== '') {
            // Interpolated, so reject anything that is not a bare identifier.
            if (preg_match('/^[a-z_][a-z0-9_]*$/i', $schema) !== 1) {
                throw new \RuntimeException("Invalid DB_SCHEMA: {$schema}");
            }
            $pdo->exec('SET search_path TO "' . $schema . '"');
        }

        return $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
