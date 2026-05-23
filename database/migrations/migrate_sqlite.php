<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function color(string $text, string $c): string
{
    $codes = ['green' => '32', 'red' => '31', 'yellow' => '33', 'cyan' => '36', 'bold' => '1'];
    $code  = $codes[$c] ?? '0';
    return "\033[{$code}m{$text}\033[0m";
}

function out(string $msg, string $c = 'bold'): void
{
    echo color($msg, $c) . PHP_EOL;
}

function abort(string $msg, int $code = 1): never
{
    out('[ERROR] ' . $msg, 'red');
    exit($code);
}

// ---------------------------------------------------------------------------
// Rewrite a PostgreSQL SQL string to be SQLite-compatible
// ---------------------------------------------------------------------------

function rewriteForSqlite(string $sql): string
{
    // 1. UUID PRIMARY KEY DEFAULT gen_random_uuid() -> TEXT PRIMARY KEY
    $sql = preg_replace(
        '/\bUUID\s+PRIMARY KEY\s+DEFAULT\s+gen_random_uuid\(\)/i',
        'TEXT PRIMARY KEY',
        $sql
    );

    // 2. UUID column references (non-PK) — strip DEFAULT gen_random_uuid() if any
    $sql = preg_replace('/\bDEFAULT\s+gen_random_uuid\(\)/i', '', $sql);

    // 3. UUID type -> TEXT (for FK columns, etc.)
    $sql = preg_replace('/\bUUID\b/i', 'TEXT', $sql);

    // 4. TIMESTAMPTZ -> TEXT
    $sql = preg_replace('/\bTIMESTAMPTZ\b/i', 'TEXT', $sql);

    // 5. JSONB -> TEXT
    $sql = preg_replace('/\bJSONB\b/i', 'TEXT', $sql);

    // 6. NUMERIC(10,2) -> REAL
    $sql = preg_replace('/\bNUMERIC\(\s*\d+\s*,\s*\d+\s*\)/i', 'REAL', $sql);

    // 7. Remove inline FK references: REFERENCES table(col) ON DELETE ...
    //    SQLite supports FK syntax but enforces them only via PRAGMA.
    //    We keep the REFERENCES clause but strip ON DELETE / ON UPDATE actions
    //    that SQLite doesn't need for PRAGMA enforcement, then re-add them cleanly.
    //    Actually SQLite DOES support ON DELETE CASCADE in column definitions
    //    when foreign_keys PRAGMA is on — so we leave the REFERENCES clauses intact
    //    and only strip the unsupported parts (none needed).
    //    Nothing to do here — SQLite supports the same syntax.

    // 8. NOW() -> (datetime('now'))
    $sql = preg_replace('/\bNOW\(\)/i', "(datetime('now'))", $sql);

    // 9. Normalize excess whitespace left by removals
    $sql = preg_replace('/[ \t]{2,}/', ' ', $sql);

    return $sql;
}

// ---------------------------------------------------------------------------
// Generate a UUID v4 in PHP
// ---------------------------------------------------------------------------

function uuidV4(): string
{
    $data    = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ---------------------------------------------------------------------------
// Parse CLI flags
// ---------------------------------------------------------------------------

$args      = array_slice($argv, 1);
$rollback  = false;
$rollbackN = 0;

for ($i = 0; $i < count($args); $i++) {
    if ($args[$i] === '--rollback') {
        $rollback  = true;
        $rollbackN = isset($args[$i + 1]) ? (int) $args[$i + 1] : 1;
        $i++;
    }
}

if ($rollback) {
    out('[INFO] Rollback is not supported for safety reasons. Migrations are forward-only.', 'yellow');
    exit(0);
}

// ---------------------------------------------------------------------------
// Load .env
// ---------------------------------------------------------------------------

$envPath = __DIR__ . '/../../';

if (file_exists($envPath . '.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

// Determine SQLite file path
$dbFile = $_ENV['DB_FILE'] ?? getenv('DB_FILE') ?: (__DIR__ . '/../../storage/database.sqlite');

// Ensure parent directory exists
$dbDir = dirname($dbFile);
if (!is_dir($dbDir)) {
    if (!mkdir($dbDir, 0755, true)) {
        abort('Could not create directory for SQLite database: ' . $dbDir);
    }
}

// ---------------------------------------------------------------------------
// Connect
// ---------------------------------------------------------------------------

try {
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    abort('Could not open SQLite database: ' . $e->getMessage());
}

// Enable foreign key enforcement
$pdo->exec('PRAGMA foreign_keys = ON');
// Use WAL for better concurrency
$pdo->exec('PRAGMA journal_mode = WAL');

out('Connected to SQLite: ' . realpath($dbFile), 'cyan');

// ---------------------------------------------------------------------------
// Ensure migrations tracking table exists
// ---------------------------------------------------------------------------

$pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS migrations (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        filename    TEXT    NOT NULL UNIQUE,
        executed_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )
SQL);

// ---------------------------------------------------------------------------
// Collect already-executed migrations
// ---------------------------------------------------------------------------

$executed = $pdo->query('SELECT filename FROM migrations ORDER BY filename')
                ->fetchAll(PDO::FETCH_COLUMN);
$executed = array_flip($executed);

// ---------------------------------------------------------------------------
// Discover SQL migration files
// ---------------------------------------------------------------------------

$files = glob(__DIR__ . '/*.sql');

if ($files === false || count($files) === 0) {
    out('No .sql migration files found.', 'yellow');
    exit(0);
}

sort($files);

// ---------------------------------------------------------------------------
// Run pending migrations
// ---------------------------------------------------------------------------

$ran    = 0;
$errors = 0;

foreach ($files as $filepath) {
    $filename = basename($filepath);

    if (isset($executed[$filename])) {
        out('  [SKIP]    ' . $filename, 'yellow');
        continue;
    }

    $rawSql = file_get_contents($filepath);

    if ($rawSql === false || trim($rawSql) === '') {
        out('  [WARN]    ' . $filename . ' — empty or unreadable, skipping', 'yellow');
        continue;
    }

    $sql = rewriteForSqlite($rawSql);

    try {
        $pdo->beginTransaction();

        // SQLite's PDO::exec() does not support multiple statements separated by ';'
        // in all builds; split on ';' and execute individually.
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s): bool => $s !== ''
        );

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
        $stmt->execute([':filename' => $filename]);
        $pdo->commit();

        out('  [OK]      ' . $filename, 'green');
        $ran++;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        out('  [FAIL]    ' . $filename . ' — ' . $e->getMessage(), 'red');
        $errors++;
        break;
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo PHP_EOL;

if ($errors > 0) {
    out(sprintf('Migration failed. %d run, %d error(s).', $ran, $errors), 'red');
    exit(1);
}

if ($ran === 0) {
    out('Nothing to migrate. All migrations already applied.', 'yellow');
} else {
    out(sprintf('Done. %d migration(s) applied successfully.', $ran), 'green');
}

exit(0);
