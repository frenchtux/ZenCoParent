<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Support;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\UploadedFile;

abstract class IntegrationTestCase extends TestCase
{
    protected App $app;
    protected \PDO $pdo;

    private static string $dbFile;
    private static string $storageDir;

    protected function setUp(): void
    {
        self::$dbFile     = sys_get_temp_dir() . '/zencoparent_test_' . uniqid() . '.sqlite';
        self::$storageDir = sys_get_temp_dir() . '/zencoparent_storage_' . uniqid();
        mkdir(self::$storageDir, 0755, true);

        $_ENV['APP_ENV']      = 'testing';
        $_ENV['APP_MODE']     = 'community';
        $_ENV['DB_FILE']      = self::$dbFile;
        $_ENV['JWT_SECRET']   = 'test-secret-that-is-long-enough-for-hs256-testing';
        $_ENV['CSRF_SECRET']  = 'test-csrf-secret';
        $_ENV['APP_SECRET']   = 'test-app-secret';
        $_ENV['APP_DEBUG']    = 'true';
        $_ENV['STORAGE_PATH'] = self::$storageDir;
        $_ENV['STORAGE_URL']  = 'http://localhost/storage';

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
        $this->removeDirectory(self::$storageDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $sub = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($sub) ? $this->removeDirectory($sub) : @unlink($sub);
        }
        @rmdir($path);
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

    protected function makeUploadRequest(
        string  $path,
        string  $fileContent,
        string  $filename,
        string  $mimeType,
        array   $fields    = [],
        array   $cookies   = [],
        ?string $csrfToken = null,
    ): ResponseInterface {
        $tmpFile = tempnam(sys_get_temp_dir(), 'zcp_upload_');
        file_put_contents($tmpFile, $fileContent);

        $uploadedFile = new UploadedFile(
            $tmpFile,
            $filename,
            $mimeType,
            strlen($fileContent),
            UPLOAD_ERR_OK,
        );

        $request = (new ServerRequestFactory())->createServerRequest(
            'POST',
            (new UriFactory())->createUri($path),
        );
        $request = $request->withUploadedFiles(['file' => $uploadedFile]);

        if (!empty($fields)) {
            $request = $request->withParsedBody($fields);
        }

        foreach ($cookies as $name => $value) {
            $request = $request->withCookieParams(array_merge($request->getCookieParams(), [$name => $value]));
        }

        if ($csrfToken !== null) {
            $request = $request->withHeader('X-CSRF-Token', $csrfToken);
        }

        $response = $this->app->handle($request);

        @unlink($tmpFile);

        return $response;
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
        bool   $mustChangeCredentials = false,
    ): string {
        $id   = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $now  = date('Y-m-d H:i:s');

        $this->pdo->prepare(
            "INSERT INTO users (id, tenant_id, email, password_hash, first_name, last_name, role, must_change_credentials, created_at, updated_at)
             VALUES (:id, :tid, :email, :hash, 'Alice', 'Test', :role, :mcc, :now, :now)"
        )->execute([
            'id'   => $id,
            'tid'  => $tenantId,
            'email'=> $email,
            'hash' => $hash,
            'role' => $role,
            'mcc'  => $mustChangeCredentials ? 1 : 0,
            'now'  => $now,
        ]);

        return $id;
    }

    protected function createMedicalRecord(
        string $tenantId,
        string $childId,
        string $createdBy,
        string $report = 'Bilan annuel RAS',
    ): string {
        $id  = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare(
            "INSERT INTO medical_records (id, tenant_id, child_id, report, recorded_at, created_by, created_at)
             VALUES (:id, :tid, :cid, :report, :now, :by, :now)"
        )->execute(['id' => $id, 'tid' => $tenantId, 'cid' => $childId, 'report' => $report, 'by' => $createdBy, 'now' => $now]);
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

    protected function createExpense(
        string $tenantId,
        string $paidBy,
        float  $amount = 50.0,
        string $date   = '2026-06-01',
    ): string {
        $id  = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare(
            "INSERT INTO expenses (id, tenant_id, paid_by, amount, description, category, split_ratio, date, created_at, updated_at)
             VALUES (:id, :tid, :paid_by, :amount, 'Test expense', NULL, '{}', :date, :now, :now)"
        )->execute(['id' => $id, 'tid' => $tenantId, 'paid_by' => $paidBy, 'amount' => $amount, 'date' => $date, 'now' => $now]);
        return $id;
    }

    protected function createThread(
        string $tenantId,
        array  $participantIds,
        string $type = 'parents',
    ): string {
        $threadId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $now      = date('Y-m-d H:i:s');

        $this->pdo->prepare(
            "INSERT INTO threads (id, tenant_id, type, created_at) VALUES (:id, :tid, :type, :now)"
        )->execute(['id' => $threadId, 'tid' => $tenantId, 'type' => $type, 'now' => $now]);

        foreach ($participantIds as $userId) {
            $this->pdo->prepare(
                "INSERT OR IGNORE INTO thread_participants (thread_id, user_id, joined_at) VALUES (:tid, :uid, :now)"
            )->execute(['tid' => $threadId, 'uid' => $userId, 'now' => $now]);
        }

        return $threadId;
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
        $migrationDir    = __DIR__ . '/../../../database/migrations';
        $sqliteOverrides = $migrationDir . '/sqlite';
        $sqlFiles        = glob($migrationDir . '/0*.sql');
        sort($sqlFiles);

        foreach ($sqlFiles as $file) {
            $filename     = basename($file);
            $overridePath = $sqliteOverrides . '/' . $filename;

            if (file_exists($overridePath)) {
                // SQLite-specific override: use as-is, no rewriting needed.
                $sql = file_get_contents($overridePath);
            } else {
                $sql = $this->rewriteForSqlite(file_get_contents($file));
            }

            foreach ($this->splitSqlStatements($sql) as $statement) {
                $this->pdo->exec($statement);
            }
        }
    }

    /**
     * Split a SQL string into individual statements, ignoring semicolons inside comments.
     * Simple line-by-line state machine: strips -- comment lines before splitting.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current    = '';

        foreach (explode("\n", $sql) as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--')) {
                // Skip comment lines — don't accumulate them to avoid semicolons inside comments.
                continue;
            }
            $current .= $line . "\n";
        }

        foreach (explode(';', $current) as $fragment) {
            $fragment = trim($fragment);
            if ($fragment !== '') {
                $statements[] = $fragment;
            }
        }

        return $statements;
    }

    private function rewriteForSqlite(string $sql): string
    {
        $sql = preg_replace('/UUID\s+PRIMARY KEY\s+DEFAULT\s+gen_random_uuid\(\)/i', 'TEXT PRIMARY KEY', $sql);
        $sql = preg_replace('/DEFAULT\s+gen_random_uuid\(\)/i', '', $sql);
        $sql = preg_replace('/TIMESTAMPTZ/i', 'TEXT', $sql);
        $sql = preg_replace('/JSONB/i', 'TEXT', $sql);
        $sql = preg_replace('/NUMERIC\(\d+,\d+\)/i', 'REAL', $sql);
        $sql = preg_replace('/SERIAL\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/DEFAULT\s+NOW\(\)/i', 'DEFAULT CURRENT_TIMESTAMP', $sql);
        return $sql;
    }
}
