<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\Settings;

use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\Settings\TenantSettingsService;

final class TenantSettingsServiceTest extends TestCase
{
    private \PDO $pdo;
    private TenantSettingsService $service;
    private string $tenantId = 'tenant-uuid-1234';

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', options: [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        // Create minimal schema
        $this->pdo->exec("
            CREATE TABLE tenant_settings (
                id         TEXT NOT NULL PRIMARY KEY,
                tenant_id  TEXT NOT NULL,
                key        TEXT NOT NULL,
                value      TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE (tenant_id, key)
            )
        ");

        $this->service = new TenantSettingsService($this->pdo, 'test-secret-key-32chars-long!!');
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->service->get($this->tenantId, 'missing_key'));
    }

    public function test_set_and_get_plain_value(): void
    {
        $this->service->set($this->tenantId, 'mail_host', 'smtp.example.com');
        $this->assertSame('smtp.example.com', $this->service->get($this->tenantId, 'mail_host'));
    }

    public function test_set_null_removes_key(): void
    {
        $this->service->set($this->tenantId, 'mail_host', 'smtp.example.com');
        $this->service->set($this->tenantId, 'mail_host', null);
        $this->assertNull($this->service->get($this->tenantId, 'mail_host'));
    }

    public function test_set_updates_existing_value(): void
    {
        $this->service->set($this->tenantId, 'mail_port', '587');
        $this->service->set($this->tenantId, 'mail_port', '465');
        $this->assertSame('465', $this->service->get($this->tenantId, 'mail_port'));
    }

    public function test_password_is_encrypted_at_rest(): void
    {
        $this->service->set($this->tenantId, 'mail_password', 'super-secret-pass');

        // Raw DB value must NOT equal the plain-text password
        $row = $this->pdo->query(
            "SELECT value FROM tenant_settings WHERE key = 'mail_password'"
        )->fetch();

        $this->assertNotSame('super-secret-pass', $row['value']);
        // But decryption via get() must return the original
        $this->assertSame('super-secret-pass', $this->service->get($this->tenantId, 'mail_password'));
    }

    public function test_get_mail_config_masks_password(): void
    {
        $this->service->setMailConfig($this->tenantId, [
            'mail_host'         => 'smtp.gmail.com',
            'mail_port'         => '587',
            'mail_username'     => 'user@example.com',
            'mail_password'     => 'my-secret',
            'mail_encryption'   => 'tls',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name'    => 'Test App',
        ]);

        $config = $this->service->getMailConfig($this->tenantId);

        $this->assertSame('smtp.gmail.com', $config['mail_host']);
        $this->assertSame('587',            $config['mail_port']);
        $this->assertSame('user@example.com', $config['mail_username']);
        $this->assertSame('••••••••',        $config['mail_password']); // masked
        $this->assertSame('tls',            $config['mail_encryption']);
    }

    public function test_set_mail_config_skips_masked_password(): void
    {
        // Set original password
        $this->service->set($this->tenantId, 'mail_password', 'original-pass');

        // Update config with masked placeholder — password must not change
        $this->service->setMailConfig($this->tenantId, [
            'mail_host'     => 'smtp.new.com',
            'mail_password' => '••••••••', // placeholder
        ]);

        $this->assertSame('original-pass', $this->service->get($this->tenantId, 'mail_password'));
        $this->assertSame('smtp.new.com',  $this->service->get($this->tenantId, 'mail_host'));
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        $tenant2 = 'other-tenant-uuid';
        $this->service->set($this->tenantId, 'mail_host', 'smtp.tenant1.com');
        $this->service->set($tenant2,         'mail_host', 'smtp.tenant2.com');

        $this->assertSame('smtp.tenant1.com', $this->service->get($this->tenantId, 'mail_host'));
        $this->assertSame('smtp.tenant2.com', $this->service->get($tenant2,         'mail_host'));
    }
}
