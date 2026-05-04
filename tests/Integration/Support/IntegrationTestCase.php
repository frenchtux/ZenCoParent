<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Support;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\UriFactory;

abstract class IntegrationTestCase extends TestCase
{
    protected App $app;
    protected \PDO $pdo;

    private static string $dbFile;

    protected function setUp(): void
    {
        self::$dbFile = sys_get_temp_dir() . '/zencoparent_test_' . uniqid() . '.sqlite';

        $_ENV['APP_ENV']   = 'testing';
        $_ENV['APP_MODE']  = 'community';
        $_ENV['DB_FILE']   = self::$dbFile;
        $_ENV['JWT_SECRET']  = 'test-secret-that-is-long-enough-for-hs256-testing';
        $_ENV['CSRF_SECRET'] = 'test-csrf-secret';
        $_ENV['APP_SECRET']  = 'test-app-secret';
        $_ENV['APP_DEBUG']   = 'true';

        $this->pdo = new \PDO('sqlite:' . self::$dbFile, options: [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');

        $this->runMigrations();

        // Reset the DB singleton so it picks up the test DB
        \ZenCoParent\Infrastructure\Database\Connection::reset();

        $this->app = require __DIR__ . '/../../../src/bootstrap/app.php';
    }

    protected function tearDown(): void
    {
        \ZenCoParent\Infrastructure\Database\Connection::reset();
        unset($this->pdo);
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }
    }

    // ─── HTTP helpers ─────────────────────────────────────────────────────────

    protected function makeRequest(
        string $method,
        string $path,
        array $body = [],
        array $cookies = [],
        array $headers = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            (new UriFactory())->createUri($path),
        );

        if (!empty($body)) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($body);
        }

        foreach ($cookies as $name => $value) {
            $request = $request->withCookieParams(array_merge($request->getCookieParams(), [$name => $value]));
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->app->handle($request);
    }

    protected function decodeJson(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    // ─── Database fixtures ────────────────────────────────────────────────────

    protected function createTenant(string $name = 'Test Family', string $slug = 'test-family'): string
    {
        $id = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->pdo->prepare(
            "INSERT INTO tenants (id, name, slug) VALUES (:id, :name, :slug)"
        )->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);
        return $id;
    }

    protected function createUser(
        string $tenantId,
        string $email = 'alice@example.com',
        string $password = 'Secret123!',
        string $role = 'parent',
    ): string {
        $id   = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $now  = date('Y-m-d H:i:s');

        $this->pdo->prepare(
            "INSERT INTO users (id, tenant_id, email, password_hash, first_name, last_name, role, created_at, updated_at)
             VALUES (:id, :tid, :email, :hash, 'Alice', 'Test', :role, :now, :now)"
        )->execute(['id' => $id, 'tid' => $tenantId, 'email' => $email, 'hash' => $hash, 'role' => $role, 'now' => $now]);

        return $id;
    }

    protected function createChild(
        string $tenantId,
        string $firstName = 'Emma',
        string $lastName  = 'Test',
        ?string $birthdate = '2015-06-15',
    ): string {
        $id  = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare(
            "INSERT INTO children (id, tenant_id, first_name, last_name, birthdate, medical_info, school_info, created_at, updated_at)
             VALUES (:id, :tid, :fn, :ln, :bd, '{}', '{}', :now, :now)"
        )->execute(['id' => $id, 'tid' => $tenantId, 'fn' => $firstName, 'ln' => $lastName, 'bd' => $birthdate, 'now' => $now]);
        return $id;
    }

    protected function createEvent(
        string $tenantId,
        string $createdBy,
        string $type = 'activity',
        ?string $childId = null,
    ): string {
        $id  = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare(
            "INSERT INTO events (id, tenant_id, child_id, title, type, start_at, end_at, all_day, created_by, created_at, updated_at)
             VALUES (:id, :tid, :cid, 'Test Event', :type, :now, :now, 0, :by, :now, :now)"
        )->execute(['id' => $id, 'tid' => $tenantId, 'cid' => $childId, 'type' => $type, 'by' => $createdBy, 'now' => $now]);
        return $id;
    }

    // ─── Migration runner ─────────────────────────────────────────────────────

    private function runMigrations(): void
    {
        $migrationDir = __DIR__ . '/../../../database/migrations';
        $sqlFiles     = glob($migrationDir . '/0*.sql');
        sort($sqlFiles);

        foreach ($sqlFiles as $file) {
            $sql = file_get_contents($file);
            $sql = $this->rewriteForSqlite($sql);

            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                $this->pdo->exec($statement);
            }
        }
    }

    private function rewriteForSqlite(string $sql): string
    {
        $sql = preg_replace('/UUID\s+PRIMARY KEY\s+DEFAULT\s+gen_random_uuid\(\)/i', 'TEXT PRIMARY KEY', $sql);
        $sql = preg_replace('/DEFAULT\s+gen_random_uuid\(\)/i', '', $sql);
        $sql = preg_replace('/TIMESTAMPTZ/i', 'TEXT', $sql);
        $sql = preg_replace('/JSONB/i', 'TEXT', $sql);
        $sql = preg_replace('/NUMERIC\(\d+,\d+\)/i', 'REAL', $sql);
        $sql = preg_replace('/SERIAL\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        return $sql;
    }
}
