<?php
declare(strict_types=1);

/**
 * ZenCoParent Community — Installation Wizard
 *
 * Run from the project root:
 *   php install.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

define('PROJECT_ROOT', __DIR__);
define('MIN_PHP_VERSION', '8.2.0');

// ── Helpers ───────────────────────────────────────────────────────────────────

function out(string $msg, string $color = ''): void
{
    $colors = [
        'green'  => "\033[0;32m",
        'yellow' => "\033[0;33m",
        'red'    => "\033[0;31m",
        'cyan'   => "\033[0;36m",
        'bold'   => "\033[1m",
        ''       => '',
    ];
    $reset = "\033[0m";
    echo ($colors[$color] ?? '') . $msg . $reset . "\n";
}

function ask(string $prompt, string $default = '', bool $hidden = false): string
{
    if ($default !== '') {
        echo $prompt . " [{$default}]: ";
    } else {
        echo $prompt . ': ';
    }

    if ($hidden && PHP_OS_FAMILY !== 'Windows') {
        system('stty -echo');
    }

    $value = trim((string) fgets(STDIN));

    if ($hidden && PHP_OS_FAMILY !== 'Windows') {
        system('stty echo');
        echo "\n";
    }

    return $value !== '' ? $value : $default;
}

function confirm(string $prompt, bool $default = true): bool
{
    $hint = $default ? '[Y/n]' : '[y/N]';
    echo $prompt . " {$hint}: ";
    $value = strtolower(trim((string) fgets(STDIN)));
    if ($value === '') {
        return $default;
    }
    return $value === 'y' || $value === 'yes';
}

function abort(string $msg): never
{
    out("\n✗ " . $msg, 'red');
    exit(1);
}

// ── 1. Banner ─────────────────────────────────────────────────────────────────

out('', '');
out('╔══════════════════════════════════════════════════╗', 'cyan');
out('║       ZenCoParent — Community Installation       ║', 'cyan');
out('╚══════════════════════════════════════════════════╝', 'cyan');
out('');

// ── 2. PHP version + extensions check ─────────────────────────────────────────

out('Checking requirements…', 'bold');

if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    abort('PHP ' . MIN_PHP_VERSION . '+ required (found ' . PHP_VERSION . ')');
}
out('  ✓ PHP ' . PHP_VERSION, 'green');

$required_extensions = ['pdo', 'pdo_sqlite', 'mbstring', 'openssl', 'json'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        abort("Required PHP extension missing: {$ext}");
    }
    out("  ✓ ext-{$ext}", 'green');
}

// ── 3. Check vendor/ ──────────────────────────────────────────────────────────

if (!file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    abort('Dependencies not installed. Run: composer install --no-dev');
}
out('  ✓ vendor/ found', 'green');
out('');

// ── 4. Gather configuration ───────────────────────────────────────────────────

out('Configuration', 'bold');
out('─────────────────────────────────────────────────', '');

$dbPath = ask('SQLite database path', PROJECT_ROOT . '/database/zencoparent.sqlite');
$storagePath = ask('File storage path', PROJECT_ROOT . '/storage');
$appUrl  = ask('Application URL (for storage links)', 'http://localhost');

out('');
out('Admin account', 'bold');
out('─────────────────────────────────────────────────', '');

$adminEmail     = ask('Admin email');
if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    abort("Invalid email address: {$adminEmail}");
}

$adminPassword = '';
while (strlen($adminPassword) < 8) {
    $adminPassword = ask('Admin password (min 8 chars)', '', hidden: true);
    if (strlen($adminPassword) < 8) {
        out('  Password must be at least 8 characters.', 'yellow');
    }
}

$adminFirstName = ask('Admin first name', 'Admin');
$adminLastName  = ask('Admin last name',  'User');

$tenantName = ask('Family/tenant name', 'My Family');
$tenantSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tenantName));
$tenantSlug = ask('Tenant slug', $tenantSlug);

out('');

// ── 5. Create directories ─────────────────────────────────────────────────────

out('Setting up directories…', 'bold');

$dbDir = dirname($dbPath);
if (!is_dir($dbDir) && !mkdir($dbDir, 0755, true) && !is_dir($dbDir)) {
    abort("Cannot create database directory: {$dbDir}");
}
out("  ✓ {$dbDir}", 'green');

if (!is_dir($storagePath) && !mkdir($storagePath, 0755, true) && !is_dir($storagePath)) {
    abort("Cannot create storage directory: {$storagePath}");
}
out("  ✓ {$storagePath}", 'green');

// ── 6. Run migrations ─────────────────────────────────────────────────────────

out('');
out('Running database migrations…', 'bold');

$pdo = new PDO('sqlite:' . $dbPath, options: [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('PRAGMA journal_mode = WAL');

$migrationDir = PROJECT_ROOT . '/database/migrations';
$sqlFiles     = glob($migrationDir . '/0*.sql');
sort($sqlFiles);

foreach ($sqlFiles as $file) {
    $sql = file_get_contents($file);
    $sql = rewriteForSqlite($sql);

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        try {
            $pdo->exec($statement);
        } catch (\PDOException $e) {
            // Ignore "already exists" errors for idempotency
            if (!str_contains($e->getMessage(), 'already exists')) {
                abort('Migration failed in ' . basename($file) . ': ' . $e->getMessage());
            }
        }
    }
    out('  ✓ ' . basename($file), 'green');
}

// ── 7. Create admin user + tenant ─────────────────────────────────────────────

out('');
out('Creating tenant and admin user…', 'bold');

require PROJECT_ROOT . '/vendor/autoload.php';

$tenantId = \Ramsey\Uuid\Uuid::uuid4()->toString();
$userId   = \Ramsey\Uuid\Uuid::uuid4()->toString();
$now      = date('Y-m-d H:i:s');
$hash     = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$pdo->prepare(
    "INSERT OR IGNORE INTO tenants (id, name, slug, created_at, updated_at)
     VALUES (:id, :name, :slug, :now, :now)"
)->execute(['id' => $tenantId, 'name' => $tenantName, 'slug' => $tenantSlug, 'now' => $now]);

$pdo->prepare(
    "INSERT OR IGNORE INTO users (id, tenant_id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
     VALUES (:id, :tid, :email, :hash, :fn, :ln, 'admin', 1, :now, :now)"
)->execute([
    'id'   => $userId,
    'tid'  => $tenantId,
    'email'=> $adminEmail,
    'hash' => $hash,
    'fn'   => $adminFirstName,
    'ln'   => $adminLastName,
    'now'  => $now,
]);

out("  ✓ Tenant '{$tenantName}' (slug: {$tenantSlug})", 'green');
out("  ✓ Admin user '{$adminEmail}'", 'green');

// ── 8. Write .env ──────────────────────────────────────────────────────────────

out('');
out('Writing .env file…', 'bold');

$jwtSecret  = bin2hex(random_bytes(32));
$csrfSecret = bin2hex(random_bytes(32));

$storageRelUrl = rtrim($appUrl, '/') . '/storage';

$env = <<<ENV
APP_ENV=production
APP_MODE=community
APP_URL={$appUrl}
APP_DEBUG=false
APP_SECRET={$jwtSecret}

DB_CONNECTION=sqlite
DB_FILE={$dbPath}

JWT_SECRET={$jwtSecret}
JWT_EXPIRY=3600

CSRF_SECRET={$csrfSecret}

STORAGE_PATH={$storagePath}
STORAGE_URL={$storageRelUrl}

RATE_LIMIT_REQUESTS=60
RATE_LIMIT_WINDOW=60
ENV;

if (file_exists(PROJECT_ROOT . '/.env') && !confirm('.env already exists — overwrite?', false)) {
    out('  Skipped .env (kept existing)', 'yellow');
} else {
    file_put_contents(PROJECT_ROOT . '/.env', $env);
    out('  ✓ .env written', 'green');
}

// ── 9. Output Nginx configuration ─────────────────────────────────────────────

$nginxConf = <<<NGINX
server {
    listen 80;
    server_name _;
    root {$appUrl};

    # Replace with your actual domain and root path, e.g.:
    # server_name zencoparent.example.com;
    # root /var/www/zencoparent/public;

    root /var/www/zencoparent/public;
    index index.php;
    charset utf-8;

    # Security headers
    add_header X-Frame-Options           "SAMEORIGIN"   always;
    add_header X-Content-Type-Options    "nosniff"      always;
    add_header X-XSS-Protection          "1; mode=block" always;
    add_header Referrer-Policy           "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy   "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self';" always;
    add_header Permissions-Policy        "camera=(), microphone=(), geolocation=()" always;

    # Static files
    location /storage/ {
        alias {$storagePath}/;
        expires 7d;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass   127.0.0.1:9000;   # or unix:/run/php/php8.2-fpm.sock
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include        fastcgi_params;
        fastcgi_read_timeout 60;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Block access to sensitive files
    location ~* \.(env|sqlite|log|sh|sql)\$ {
        deny all;
        return 404;
    }
}
NGINX;

out('');
out('─────────────────────────────────────────────────────────────────', 'cyan');
out(' Nginx configuration (save to /etc/nginx/sites-available/zencoparent.conf):', 'cyan');
out('─────────────────────────────────────────────────────────────────', 'cyan');
out($nginxConf);
out('─────────────────────────────────────────────────────────────────', 'cyan');

// ── 10. Done ──────────────────────────────────────────────────────────────────

out('');
out('╔══════════════════════════════════════════════════╗', 'green');
out('║          Installation complete! ✓                ║', 'green');
out('╚══════════════════════════════════════════════════╝', 'green');
out('');
out("  Application URL : {$appUrl}", 'bold');
out("  Admin login     : {$adminEmail}", 'bold');
out("  Database        : {$dbPath}", 'bold');
out("  Storage         : {$storagePath}", 'bold');
out('');
out('  Delete install.php after setup!', 'yellow');
out('');

// ── Helpers ───────────────────────────────────────────────────────────────────

function rewriteForSqlite(string $sql): string
{
    $sql = preg_replace('/UUID\s+PRIMARY KEY\s+DEFAULT\s+gen_random_uuid\(\)/i', 'TEXT PRIMARY KEY', $sql);
    $sql = preg_replace('/DEFAULT\s+gen_random_uuid\(\)/i', '', $sql);
    $sql = preg_replace('/TIMESTAMPTZ/i', 'TEXT', $sql);
    $sql = preg_replace('/JSONB/i', 'TEXT', $sql);
    $sql = preg_replace('/NUMERIC\(\d+,\d+\)/i', 'REAL', $sql);
    $sql = preg_replace('/SERIAL\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    return $sql;
}
