<?php
/**
 * Seed script — crée un tenant + un admin pour les tests locaux en mode community.
 * Usage : php seed_admin.php
 */
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

Dotenv::createImmutable(__DIR__)->load();

$dbFile = $_ENV['DB_FILE'] ?? __DIR__ . '/storage/database.sqlite';
$pdo = new PDO("sqlite:{$dbFile}", options: [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');

function uuid4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

// ── Tenant ────────────────────────────────────────────────────────────────────
$tenantSlug = 'zencoparent';
$row = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug');
$row->execute(['slug' => $tenantSlug]);
$tenant = $row->fetch();

if ($tenant) {
    $tenantId = $tenant['id'];
    echo "[SKIP] Tenant '{$tenantSlug}' already exists (id={$tenantId})\n";
} else {
    $tenantId = uuid4();
    $pdo->prepare(
        "INSERT INTO tenants (id, name, slug, is_active, created_at, updated_at)
         VALUES (:id, :name, :slug, 1, datetime('now'), datetime('now'))"
    )->execute(['id' => $tenantId, 'name' => 'ZenCoParent', 'slug' => $tenantSlug]);
    echo "[OK]   Tenant '{$tenantSlug}' créé (id={$tenantId})\n";
}

// ── Admin user ────────────────────────────────────────────────────────────────
$email    = 'admin@zencoparent.local';
$password = 'Admin1234!';

$row = $pdo->prepare('SELECT id FROM users WHERE tenant_id = :tid AND email = :email');
$row->execute(['tid' => $tenantId, 'email' => $email]);
$user = $row->fetch();

if ($user) {
    echo "[SKIP] User '{$email}' already exists (id={$user['id']})\n";
} else {
    $userId = uuid4();
    $hash   = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare(
        "INSERT INTO users (id, tenant_id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
         VALUES (:id, :tid, :email, :hash, 'Admin', 'ZenCoParent', 'admin', 1, datetime('now'), datetime('now'))"
    )->execute([
        'id'    => $userId,
        'tid'   => $tenantId,
        'email' => $email,
        'hash'  => $hash,
    ]);
    echo "[OK]   User '{$email}' créé (id={$userId})\n";
}

// ── Parent user (pour tester le flow co-parental) ─────────────────────────────
$parentEmail = 'parent@zencoparent.local';
$row = $pdo->prepare('SELECT id FROM users WHERE tenant_id = :tid AND email = :email');
$row->execute(['tid' => $tenantId, 'email' => $parentEmail]);
$parent = $row->fetch();

if ($parent) {
    echo "[SKIP] User '{$parentEmail}' already exists\n";
} else {
    $parentId = uuid4();
    $hash2    = password_hash('Parent1234!', PASSWORD_BCRYPT);
    $pdo->prepare(
        "INSERT INTO users (id, tenant_id, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
         VALUES (:id, :tid, :email, :hash, 'Parent', 'ZenCoParent', 'parent', 1, datetime('now'), datetime('now'))"
    )->execute([
        'id'    => $parentId,
        'tid'   => $tenantId,
        'email' => $parentEmail,
        'hash'  => $hash2,
    ]);
    echo "[OK]   User '{$parentEmail}' créé (id={$parentId})\n";
}

echo "\n";
echo "══════════════════════════════════════════════════════\n";
echo "  Comptes par défaut (Community — SQLite) :\n";
echo "  Tenant slug   : {$tenantSlug}\n";
echo "  Login admin   : {$email}\n";
echo "  Mot de passe  : Admin1234!\n";
echo "  Login parent  : {$parentEmail}\n";
echo "  Mot de passe  : Parent1234!\n";
echo "══════════════════════════════════════════════════════\n";
