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
// Parse CLI flags
// ---------------------------------------------------------------------------

$args     = array_slice($argv, 1);
$rollback = false;
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
// Load .env (or fall back to process environment, e.g. when running in Docker)
// ---------------------------------------------------------------------------

$envPath = __DIR__ . '/../../';
$envFile = getenv('ZENCO_ENV_FILE') ?: '.env';

if (file_exists($envPath . $envFile)) {
    Dotenv::createImmutable($envPath, $envFile)->load();
} else {
    // No .env file — populate $_ENV from process environment (Docker env_file / -e flags).
    foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
        if (($val = getenv($key)) !== false) {
            $_ENV[$key] = $val;
        }
    }
}

// Required variables
$missing = array_filter(
    ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'],
    fn(string $k): bool => empty($_ENV[$k])
);
if (!empty($missing)) {
    abort('Missing required environment variables: ' . implode(', ', $missing));
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'],
    $_ENV['DB_DATABASE']
);

// ---------------------------------------------------------------------------
// Connect
// ---------------------------------------------------------------------------

try {
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    abort('Could not connect to PostgreSQL: ' . $e->getMessage());
}

out('Connected to PostgreSQL (' . $_ENV['DB_HOST'] . ':' . $_ENV['DB_PORT'] . '/' . $_ENV['DB_DATABASE'] . ')', 'cyan');

// ---------------------------------------------------------------------------
// Ensure migrations tracking table exists
// ---------------------------------------------------------------------------

$pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS migrations (
        id          SERIAL      PRIMARY KEY,
        filename    TEXT        NOT NULL UNIQUE,
        executed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
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

    $sql = file_get_contents($filepath);

    if ($sql === false || trim($sql) === '') {
        out('  [WARN]    ' . $filename . ' — empty or unreadable, skipping', 'yellow');
        continue;
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
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
        // Stop on first failure to preserve referential integrity ordering
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
