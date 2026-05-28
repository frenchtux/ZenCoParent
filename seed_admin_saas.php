<?php
/**
 * Seed script — crée le tenant par défaut + un admin pour la version SaaS (PostgreSQL).
 * Usage : php seed_admin_saas.php
 *         (exécuté automatiquement par le conteneur `seed` dans docker-compose.yml)
 */
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$envFile = $_ENV['ZENCO_ENV_FILE'] ?? getenv('ZENCO_ENV_FILE') ?: '.env.saas';
Dotenv::createImmutable(__DIR__, $envFile)->load();

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $_ENV['DB_HOST']     ?? 'postgres',
    $_ENV['DB_PORT']     ?? '5432',
    $_ENV['DB_DATABASE'] ?? 'zencoparent',
);

$pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'zencoparent', $_ENV['DB_PASSWORD'] ?? 'secret', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ── Tenant ─────────────────────────────────────────────────────────────────────
$tenantSlug = 'zencoparent';
$row = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug');
$row->execute(['slug' => $tenantSlug]);
$tenant = $row->fetch();

if ($tenant) {
    $tenantId = $tenant['id'];
    echo "[SKIP] Tenant '{$tenantSlug}' already exists (id={$tenantId})\n";
} else {
    $pdo->prepare(
        "INSERT INTO tenants (name, slug, is_active, created_at, updated_at)
         VALUES (:name, :slug, true, NOW(), NOW())
         RETURNING id"
    )->execute(['name' => 'ZenCoParent', 'slug' => $tenantSlug]);
    // Retrieve generated UUID
    $row = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug');
    $row->execute(['slug' => $tenantSlug]);
    $tenantId = $row->fetchColumn();
    echo "[OK]   Tenant '{$tenantSlug}' créé (id={$tenantId})\n";
}

// ── Admin user ─────────────────────────────────────────────────────────────────
$email    = 'admin@zencoparent.local';
$password = 'Admin1234!';

$row = $pdo->prepare('SELECT id FROM users WHERE tenant_id = :tid AND email = :email');
$row->execute(['tid' => $tenantId, 'email' => $email]);
$user = $row->fetch();

if ($user) {
    echo "[SKIP] User '{$email}' already exists (id={$user['id']})\n";
} else {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare(
        "INSERT INTO users (tenant_id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
         VALUES (:tid, :email, :hash, 'Admin', 'ZenCoParent', 'admin', true, NOW(), NOW())"
    )->execute(['tid' => $tenantId, 'email' => $email, 'hash' => $hash]);
    $row = $pdo->prepare('SELECT id FROM users WHERE tenant_id = :tid AND email = :email');
    $row->execute(['tid' => $tenantId, 'email' => $email]);
    $userId = $row->fetchColumn();
    echo "[OK]   User '{$email}' créé (id={$userId})\n";
}

// ── Parent user ────────────────────────────────────────────────────────────────
$parentEmail = 'parent@zencoparent.local';
$row = $pdo->prepare('SELECT id FROM users WHERE tenant_id = :tid AND email = :email');
$row->execute(['tid' => $tenantId, 'email' => $parentEmail]);
$parent = $row->fetch();

if ($parent) {
    echo "[SKIP] User '{$parentEmail}' already exists\n";
} else {
    $hash2 = password_hash('Parent1234!', PASSWORD_BCRYPT);
    $pdo->prepare(
        "INSERT INTO users (tenant_id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
         VALUES (:tid, :email, :hash, 'Parent', 'ZenCoParent', 'parent', true, NOW(), NOW())"
    )->execute(['tid' => $tenantId, 'email' => $parentEmail, 'hash' => $hash2]);
    echo "[OK]   User '{$parentEmail}' créé\n";
}

echo "\n";
echo "══════════════════════════════════════════════════════\n";
echo "  Comptes par défaut (SaaS — PostgreSQL) :\n";
echo "  Tenant slug   : {$tenantSlug}\n";
echo "  Login admin   : {$email}\n";
echo "  Mot de passe  : Admin1234!\n";
echo "  Login parent  : {$parentEmail}\n";
echo "  Mot de passe  : Parent1234!\n";
echo "══════════════════════════════════════════════════════\n";
