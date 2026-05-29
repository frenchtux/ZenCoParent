<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\License;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ZenCoParent\Application\License\LicenseService;
use ZenCoParent\Domain\License\License;
use ZenCoParent\Domain\License\LicenseRepositoryInterface;

final class LicenseServiceTest extends TestCase
{
    private const INSTALL_KEY = 'ZNCO-ABCD-1234-EFGH-5678-IJKL';

    private string $privateKey;
    private string $publicKey;
    private string $publicKeyHex;

    private MockInterface $repo;
    private MockInterface $logger;
    private LicenseService $service;

    protected function setUp(): void
    {
        // Generate a fresh Ed25519 keypair for each test run
        $keypair            = sodium_crypto_sign_keypair();
        $this->privateKey   = sodium_crypto_sign_secretkey($keypair);
        $this->publicKey    = sodium_crypto_sign_publickey($keypair);
        $this->publicKeyHex = bin2hex($this->publicKey);

        $this->repo    = Mockery::mock(LicenseRepositoryInterface::class);
        $this->logger  = Mockery::mock(LoggerInterface::class);
        $this->service = new LicenseService($this->repo, $this->publicKeyHex, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ── activate — happy path ─────────────────────────────────────────────────

    public function test_activate_returns_true_with_valid_token(): void
    {
        $license = $this->makeInactiveLicense();
        $token   = $this->buildToken(self::INSTALL_KEY);

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once()->with(Mockery::on(
            fn(License $l) => $l->isActive() && $l->getMachineFingerprint() !== null
        ));

        $this->assertTrue($this->service->activate($token));
    }

    public function test_activate_stores_customer_email_and_expiry(): void
    {
        $license   = $this->makeInactiveLicense();
        $expiresAt = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $token     = $this->buildToken(self::INSTALL_KEY, 'client@example.com', $expiresAt);

        $captured = null;
        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once()->with(Mockery::on(function (License $l) use (&$captured) {
            $captured = $l;
            return true;
        }));

        $this->service->activate($token);

        $this->assertSame('client@example.com', $captured->getCustomerEmail());
        $this->assertNotNull($captured->getExpiresAt());
    }

    public function test_activate_stores_machine_fingerprint(): void
    {
        $license  = $this->makeInactiveLicense();
        $token    = $this->buildToken(self::INSTALL_KEY);
        $captured = null;

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once()->with(Mockery::on(function (License $l) use (&$captured) {
            $captured = $l;
            return true;
        }));

        $this->service->activate($token);

        $this->assertIsString($captured->getMachineFingerprint());
        $this->assertSame(64, strlen($captured->getMachineFingerprint()));
    }

    // ── activate — rejection cases ────────────────────────────────────────────

    public function test_activate_returns_false_with_tampered_signature(): void
    {
        $license = $this->makeInactiveLicense();
        $token   = $this->buildToken(self::INSTALL_KEY);

        // Replace the signature part (after the dot) with a random one of the same length
        $dot       = strrpos($token, '.');
        $sigPart   = substr($token, $dot + 1);
        $fakeSig   = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $corrupted = substr($token, 0, $dot + 1) . $fakeSig;

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->never();

        $this->assertFalse($this->service->activate($corrupted));
    }

    public function test_activate_returns_false_with_wrong_installation_key(): void
    {
        $license = $this->makeInactiveLicense(); // has INSTALL_KEY
        $token   = $this->buildToken('ZNCO-FFFF-FFFF-FFFF-FFFF-FFFF'); // different key

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->never();

        $this->assertFalse($this->service->activate($token));
    }

    public function test_activate_returns_false_with_expired_token(): void
    {
        $license   = $this->makeInactiveLicense();
        $expiresAt = (new \DateTimeImmutable('-1 day'))->format(\DateTimeInterface::ATOM);
        $token     = $this->buildToken(self::INSTALL_KEY, null, $expiresAt);

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->never();

        $this->assertFalse($this->service->activate($token));
    }

    public function test_activate_returns_false_with_malformed_token(): void
    {
        $license = $this->makeInactiveLicense();

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->never();

        $this->assertFalse($this->service->activate('not-a-valid-token'));
    }

    public function test_activate_accepts_token_without_expiry(): void
    {
        $license = $this->makeInactiveLicense();
        $token   = $this->buildToken(self::INSTALL_KEY, null, null); // perpetual

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once();

        $this->assertTrue($this->service->activate($token));
    }

    // ── revoke ────────────────────────────────────────────────────────────────

    public function test_revoke_marks_active_license_as_revoked(): void
    {
        $license = $this->makeActiveLicense();

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once()->with(Mockery::on(
            fn(License $l) => $l->isRevoked() && $l->getRevokedAt() !== null
        ));

        $this->assertTrue($this->service->revoke());
    }

    public function test_revoke_returns_false_when_no_license(): void
    {
        $this->repo->shouldReceive('get')->andReturn(null);
        $this->repo->shouldReceive('update')->never();

        $this->assertFalse($this->service->revoke());
    }

    public function test_revoke_returns_false_when_already_revoked(): void
    {
        $license = $this->makeRevokedLicense();

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->never();

        $this->assertFalse($this->service->revoke());
    }

    // ── isLicensed ────────────────────────────────────────────────────────────

    public function test_revoked_license_is_not_licensed(): void
    {
        $this->assertFalse($this->makeRevokedLicense()->isLicensed());
    }

    public function test_active_non_revoked_license_is_licensed(): void
    {
        $this->assertTrue($this->makeActiveLicense()->isLicensed());
    }

    public function test_trial_license_is_licensed_without_activation(): void
    {
        $this->assertTrue($this->makeInactiveLicense()->isLicensed());
    }

    public function test_active_expired_license_is_not_licensed(): void
    {
        $license = License::fromArray([
            'id'                  => 'eeeeeeee-0000-0000-0000-eeeeeeeeeeee',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => 'some-token',
            'installed_at'        => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'activated_at'        => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'is_active'           => true,
            'instance_id'         => 'test-host',
            'revoked_at'          => null,
            'machine_fingerprint' => null,
            'customer_email'      => null,
            'expires_at'          => (new \DateTimeImmutable('-1 second'))->format('Y-m-d H:i:s'),
        ]);

        $this->assertFalse($license->isLicensed());
    }

    // ── checkFingerprint ──────────────────────────────────────────────────────

    public function test_check_fingerprint_does_nothing_when_no_fingerprint_stored(): void
    {
        $license = $this->makeActiveLicense();
        $this->logger->shouldReceive('warning')->never();

        $this->service->checkFingerprint($license);

        $this->expectNotToPerformAssertions();
    }

    public function test_check_fingerprint_logs_warning_on_mismatch(): void
    {
        $license = $this->makeActiveLicenseWithFingerprint('0000000000000000000000000000000000000000000000000000000000000000');

        $this->logger->shouldReceive('warning')
            ->once()
            ->with(Mockery::on(fn(string $msg) => str_contains($msg, 'fingerprint mismatch')), Mockery::type('array'));

        $this->service->checkFingerprint($license);

        $this->addToAssertionCount(1);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Build a real signed Ed25519 token for testing. */
    private function buildToken(string $installationKey, ?string $email = null, ?string $expiresAt = 'skip'): string
    {
        $payload = [
            'v'                => 2,
            'installation_key' => $installationKey,
            'customer_email'   => $email,
            'issued_at'        => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'expires_at'       => $expiresAt === 'skip' ? null : $expiresAt,
        ];

        $json      = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = sodium_crypto_sign_detached($json, $this->privateKey);

        $b64url = static fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $b64url($json) . '.' . $b64url($signature);
    }

    private function makeInactiveLicense(): License
    {
        return License::fromArray([
            'id'                  => 'aaaaaaaa-0000-0000-0000-aaaaaaaaaaaa',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => null,
            'installed_at'        => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'activated_at'        => null,
            'is_active'           => false,
            'instance_id'         => 'test-host',
            'revoked_at'          => null,
            'machine_fingerprint' => null,
            'customer_email'      => null,
            'expires_at'          => null,
        ]);
    }

    private function makeActiveLicense(): License
    {
        return License::fromArray([
            'id'                  => 'bbbbbbbb-0000-0000-0000-bbbbbbbbbbbb',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => 'some-token',
            'installed_at'        => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'activated_at'        => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'is_active'           => true,
            'instance_id'         => 'test-host',
            'revoked_at'          => null,
            'machine_fingerprint' => null,
            'customer_email'      => null,
            'expires_at'          => null,
        ]);
    }

    private function makeActiveLicenseWithFingerprint(string $fingerprint): License
    {
        return License::fromArray([
            'id'                  => 'cccccccc-0000-0000-0000-cccccccccccc',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => 'some-token',
            'installed_at'        => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'activated_at'        => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'is_active'           => true,
            'instance_id'         => 'test-host',
            'revoked_at'          => null,
            'machine_fingerprint' => $fingerprint,
            'customer_email'      => null,
            'expires_at'          => null,
        ]);
    }

    private function makeRevokedLicense(): License
    {
        return License::fromArray([
            'id'                  => 'dddddddd-0000-0000-0000-dddddddddddd',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => 'some-token',
            'installed_at'        => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'activated_at'        => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'is_active'           => true,
            'instance_id'         => 'test-host',
            'revoked_at'          => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'machine_fingerprint' => null,
            'customer_email'      => null,
            'expires_at'          => null,
        ]);
    }
}
