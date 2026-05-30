<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Settings;

/**
 * Reads and writes tenant-scoped key/value settings.
 * Sensitive values (MAIL_PASSWORD) are AES-256-CBC encrypted with APP_SECRET.
 */
final class TenantSettingsService
{
    /** @deprecated use getSystemSetting/setSystemSetting — kept for backwards compat */
    public const SYSTEM_TENANT = '__system__';

    private const SENSITIVE_KEYS = [
        'mail_password',
        'oauth_google_client_secret',
        'paypal_client_secret',
    ];

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

    public const OAUTH_KEYS = [
        'oauth_google_enabled',
        'oauth_google_client_id',
        'oauth_google_client_secret',
    ];

    public const APP_KEYS = [
        'app_name',
        'app_url',
    ];

    public const SECURITY_KEYS = [
        'jwt_expiry',
        'jwt_refresh_expiry',
        'rate_limit_requests',
        'rate_limit_window',
    ];

    public const PAYMENT_KEYS = [
        'paypal_client_id',
        'paypal_client_secret',
        'paypal_mode',
        'paypal_webhook_id',
    ];

    public function __construct(
        private readonly \PDO    $pdo,
        private readonly string  $encryptionKey,
    ) {}

    // ── Read ──────────────────────────────────────────────────────────────────

    public function get(string $tenantId, string $key): ?string
    {
        if ($tenantId === self::SYSTEM_TENANT) {
            $stmt = $this->pdo->prepare('SELECT value FROM app_settings WHERE key = :key');
            $stmt->execute(['key' => $key]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT value FROM tenant_settings WHERE tenant_id = :tid AND key = :key'
            );
            $stmt->execute(['tid' => $tenantId, 'key' => $key]);
        }

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
        if ($tenantId === self::SYSTEM_TENANT) {
            if ($value === null) {
                $this->pdo->prepare('DELETE FROM app_settings WHERE key = :key')
                    ->execute(['key' => $key]);
                return;
            }
            $stored = in_array($key, self::SENSITIVE_KEYS, true) ? $this->encrypt($value) : $value;
            $this->upsertAppSetting($key, $stored);
            return;
        }

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
            $this->set($tenantId, $k, ($v === '' || $v === null) ? null : (string) $v);
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

    // ── OAuth (system-level) ─────────────────────────────────────────────────

    public function getOAuthConfig(): array
    {
        return $this->getSystemGroup(self::OAUTH_KEYS, mask: ['oauth_google_client_secret']);
    }

    public function setOAuthConfig(array $data): void
    {
        $this->setSystemGroup(self::OAUTH_KEYS, $data, mask: ['oauth_google_client_secret']);
    }

    // ── App (system-level) ───────────────────────────────────────────────────

    public function getAppConfig(): array
    {
        return $this->getSystemGroup(self::APP_KEYS);
    }

    public function setAppConfig(array $data): void
    {
        $this->setSystemGroup(self::APP_KEYS, $data);
    }

    // ── Security (system-level) ──────────────────────────────────────────────

    public function getSecurityConfig(): array
    {
        return $this->getSystemGroup(self::SECURITY_KEYS);
    }

    public function setSecurityConfig(array $data): void
    {
        $this->setSystemGroup(self::SECURITY_KEYS, $data);
    }

    // ── Payment (system-level) ───────────────────────────────────────────────

    public function getPaymentConfig(): array
    {
        return $this->getSystemGroup(self::PAYMENT_KEYS, mask: ['paypal_client_secret']);
    }

    public function setPaymentConfig(array $data): void
    {
        $this->setSystemGroup(self::PAYMENT_KEYS, $data, mask: ['paypal_client_secret']);
    }

    /** Read a single system-level setting from app_settings. */
    public function getSystemSetting(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM app_settings WHERE key = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetchColumn();
        if ($row === false || $row === null) {
            return null;
        }
        return in_array($key, self::SENSITIVE_KEYS, true) ? $this->decrypt((string) $row) : (string) $row;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function getSystemGroup(array $keys, array $mask = []): array
    {
        $placeholders = implode(',', array_map(fn($i) => ":k{$i}", array_keys($keys)));
        $params = [];
        foreach ($keys as $i => $k) {
            $params["k{$i}"] = $k;
        }
        $stmt = $this->pdo->prepare(
            "SELECT key, value FROM app_settings WHERE key IN ({$placeholders})"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $config = [];
        foreach ($keys as $k) {
            $raw = $rows[$k] ?? null;
            if ($raw === null) {
                $config[$k] = null;
            } elseif (in_array($k, $mask, true)) {
                $config[$k] = '••••••••';
            } elseif (in_array($k, self::SENSITIVE_KEYS, true)) {
                $config[$k] = $this->decrypt($raw);
            } else {
                $config[$k] = $raw;
            }
        }
        return $config;
    }

    private function setSystemGroup(array $keys, array $data, array $mask = []): void
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            if (in_array($k, $mask, true) && $v === '••••••••') {
                continue;
            }
            $this->upsertSystem($k, ($v === '' || $v === null) ? null : (string) $v);
        }
    }

    private function upsertSystem(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->pdo->prepare('DELETE FROM app_settings WHERE key = :key')
                ->execute(['key' => $key]);
            return;
        }

        $stored = in_array($key, self::SENSITIVE_KEYS, true) ? $this->encrypt($value) : $value;
        $now    = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $upd = $this->pdo->prepare(
            'UPDATE app_settings SET value = :val, updated_at = :now WHERE key = :key'
        );
        $upd->execute(['val' => $stored, 'now' => $now, 'key' => $key]);

        if ($upd->rowCount() === 0) {
            $this->pdo->prepare(
                'INSERT INTO app_settings (key, value, created_at, updated_at) VALUES (:key, :val, :now, :now)'
            )->execute(['key' => $key, 'val' => $stored, 'now' => $now]);
        }
    }

    private function getGroup(string $tenantId, array $keys, array $mask = []): array
    {
        $placeholders = implode(',', array_map(fn($i) => ":k{$i}", array_keys($keys)));
        $params = [];
        foreach ($keys as $i => $k) {
            $params["k{$i}"] = $k;
        }

        if ($tenantId === self::SYSTEM_TENANT) {
            $stmt = $this->pdo->prepare(
                "SELECT key, value FROM app_settings WHERE key IN ({$placeholders})"
            );
        } else {
            $params['tid'] = $tenantId;
            $stmt = $this->pdo->prepare(
                "SELECT key, value FROM tenant_settings WHERE tenant_id = :tid AND key IN ({$placeholders})"
            );
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $config = [];
        foreach ($keys as $k) {
            $raw = $rows[$k] ?? null;
            if ($raw === null) {
                $config[$k] = null;
            } elseif (in_array($k, $mask, true)) {
                $config[$k] = '••••••••';
            } elseif (in_array($k, self::SENSITIVE_KEYS, true)) {
                $config[$k] = $this->decrypt($raw);
            } else {
                $config[$k] = $raw;
            }
        }
        return $config;
    }

    private function setGroup(string $tenantId, array $keys, array $data, array $mask = []): void
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            if (in_array($k, $mask, true) && $v === '••••••••') {
                continue; // keep existing value
            }
            $this->set($tenantId, $k, ($v === '' || $v === null) ? null : (string) $v);
        }
    }

    private function upsertAppSetting(string $key, string $value): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $upd = $this->pdo->prepare(
            'UPDATE app_settings SET value = :val, updated_at = :now WHERE key = :key'
        );
        $upd->execute(['val' => $value, 'now' => $now, 'key' => $key]);
        if ($upd->rowCount() === 0) {
            $this->pdo->prepare(
                'INSERT INTO app_settings (key, value, created_at, updated_at) VALUES (:key, :val, :now, :now)'
            )->execute(['key' => $key, 'val' => $value, 'now' => $now]);
        }
    }

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
