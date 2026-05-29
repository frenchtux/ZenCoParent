<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Settings;

/**
 * Reads and writes tenant-scoped key/value settings.
 * Sensitive values (MAIL_PASSWORD) are AES-256-CBC encrypted with APP_SECRET.
 */
final class TenantSettingsService
{
    private const SENSITIVE_KEYS = ['mail_password'];

    // SMTP keys exposed through the API
    public const MAIL_KEYS = [
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    public function __construct(
        private readonly \PDO    $pdo,
        private readonly string  $encryptionKey,
    ) {}

    // ── Read ──────────────────────────────────────────────────────────────────

    public function get(string $tenantId, string $key): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT value FROM tenant_settings WHERE tenant_id = :tid AND key = :key'
        );
        $stmt->execute(['tid' => $tenantId, 'key' => $key]);
        $row = $stmt->fetchColumn();

        if ($row === false || $row === null) {
            return null;
        }

        return in_array($key, self::SENSITIVE_KEYS, true) ? $this->decrypt((string) $row) : (string) $row;
    }

    /** Return all mail settings for a tenant (password masked in response). */
    public function getMailConfig(string $tenantId): array
    {
        $placeholders = implode(',', array_map(fn($i) => ":k{$i}", array_keys(self::MAIL_KEYS)));
        $params = ['tid' => $tenantId];
        foreach (self::MAIL_KEYS as $i => $k) {
            $params["k{$i}"] = $k;
        }
        $stmt = $this->pdo->prepare(
            "SELECT key, value FROM tenant_settings WHERE tenant_id = :tid AND key IN ({$placeholders})"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $config = [];
        foreach (self::MAIL_KEYS as $k) {
            $raw = $rows[$k] ?? null;
            if ($raw === null) {
                $config[$k] = null;
            } elseif ($k === 'mail_password') {
                // Never return the actual password — return a placeholder
                $config[$k] = '••••••••';
            } else {
                $config[$k] = $raw;
            }
        }
        return $config;
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function set(string $tenantId, string $key, ?string $value): void
    {
        if ($value === null) {
            $this->pdo->prepare(
                'DELETE FROM tenant_settings WHERE tenant_id = :tid AND key = :key'
            )->execute(['tid' => $tenantId, 'key' => $key]);
            return;
        }

        $stored = in_array($key, self::SENSITIVE_KEYS, true) ? $this->encrypt($value) : $value;

        $this->upsert($tenantId, $key, $stored);
    }

    /** Save all mail settings at once; skip password if value is the placeholder. */
    public function setMailConfig(string $tenantId, array $data): void
    {
        foreach (self::MAIL_KEYS as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            // Skip the masked placeholder — keep existing password
            if ($k === 'mail_password' && $v === '••••••••') {
                continue;
            }
            $this->set($tenantId, $k, $v === '' ? null : $v);
        }
    }

    // ── Build a SmtpMailer from tenant DB settings ────────────────────────────

    public function buildMailer(string $tenantId): ?\ZenCoParent\Domain\Notification\MailerInterface
    {
        $host = $this->get($tenantId, 'mail_host');
        if (empty($host)) {
            return null;
        }
        return new \ZenCoParent\Infrastructure\Notification\SmtpMailer(
            host:        $host,
            port:        (int) ($this->get($tenantId, 'mail_port') ?? 587),
            username:    $this->get($tenantId, 'mail_username') ?? '',
            password:    $this->get($tenantId, 'mail_password') ?? '',
            encryption:  $this->get($tenantId, 'mail_encryption') ?? 'tls',
            fromAddress: $this->get($tenantId, 'mail_from_address') ?? 'noreply@zencoparent.com',
            fromName:    $this->get($tenantId, 'mail_from_name') ?? 'ZenCoParent',
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function upsert(string $tenantId, string $key, string $value): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Try UPDATE first
        $upd = $this->pdo->prepare(
            'UPDATE tenant_settings SET value = :val, updated_at = :now WHERE tenant_id = :tid AND key = :key'
        );
        $upd->execute(['val' => $value, 'now' => $now, 'tid' => $tenantId, 'key' => $key]);

        if ($upd->rowCount() === 0) {
            // Row doesn't exist — INSERT
            $ins = $this->pdo->prepare(
                'INSERT INTO tenant_settings (id, tenant_id, key, value, created_at, updated_at)
                 VALUES (:id, :tid, :key, :val, :now, :now)'
            );
            $ins->execute([
                'id'  => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'tid' => $tenantId,
                'key' => $key,
                'val' => $value,
                'now' => $now,
            ]);
        }
    }

    private function encrypt(string $plain): string
    {
        $key  = substr(hash('sha256', $this->encryptionKey, true), 0, 32);
        $iv   = random_bytes(16);
        $enc  = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    private function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext);
        $key = substr(hash('sha256', $this->encryptionKey, true), 0, 32);
        $iv  = substr($raw, 0, 16);
        $enc = substr($raw, 16);
        $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $dec !== false ? $dec : '';
    }
}
