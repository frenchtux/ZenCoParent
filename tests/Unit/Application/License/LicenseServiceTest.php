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
    private const MASTER_KEY     = 'test-master-key-for-unit-tests';
    private const INSTALL_KEY    = 'ZNCO-ABCD-1234-EFGH-5678-IJKL';

    private MockInterface $repo;
    private MockInterface $logger;
    private LicenseService $service;

    protected function setUp(): void
    {
        $this->repo    = Mockery::mock(LicenseRepositoryInterface::class);
        $this->logger  = Mockery::mock(LoggerInterface::class);
        $this->service = new LicenseService($this->repo, self::MASTER_KEY, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ── deriveActivationKey ───────────────────────────────────────────────────

    public function test_derive_activation_key_has_correct_format(): void
    {
        $key = $this->service->deriveActivationKey(self::INSTALL_KEY);
        $this->assertMatchesRegularExpression('/^ACT-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', $key);
    }

    public function test_derive_activation_key_is_deterministic(): void
    {
        $a = $this->service->deriveActivationKey(self::INSTALL_KEY);
        $b = $this->service->deriveActivationKey(self::INSTALL_KEY);
        $this->assertSame($a, $b);
    }

    // ── activate ─────────────────────────────────────────────────────────────

    public function test_activate_returns_true_with_correct_key(): void
    {
        $license    = $this->makeInactiveLicense();
        $activationKey = $this->service->deriveActivationKey(self::INSTALL_KEY);

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once()->with(Mockery::on(
            fn(License $l) => $l->isActive() && $l->getMachineFingerprint() !== null
        ));

        $result = $this->service->activate($activationKey);

        $this->assertTrue($result);
    }

    public function test_activate_returns_false_with_wrong_key(): void
    {
        $license = $this->makeInactiveLicense();
        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->never();

        $result = $this->service->activate('ACT-BAAD-BAAD-BAAD-BAAD-BAAD');

        $this->assertFalse($result);
    }

    public function test_activate_stores_machine_fingerprint(): void
    {
        $license       = $this->makeInactiveLicense();
        $activationKey = $this->service->deriveActivationKey(self::INSTALL_KEY);
        $captured      = null;

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once()->with(Mockery::on(function (License $l) use (&$captured) {
            $captured = $l;
            return true;
        }));

        $this->service->activate($activationKey);

        $this->assertNotNull($captured);
        $this->assertIsString($captured->getMachineFingerprint());
        $this->assertSame(64, strlen($captured->getMachineFingerprint()));
    }

    // ── revoke ────────────────────────────────────────────────────────────────

    public function test_revoke_marks_active_license_as_revoked(): void
    {
        $license = $this->makeActiveLicense();

        $this->repo->shouldReceive('get')->andReturn($license);
        $this->repo->shouldReceive('update')->once()->with(Mockery::on(
            fn(License $l) => $l->isRevoked() && $l->getRevokedAt() !== null
        ));

        $result = $this->service->revoke();

        $this->assertTrue($result);
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

    // ── isLicensed with revocation ────────────────────────────────────────────

    public function test_revoked_license_is_not_licensed(): void
    {
        $license = $this->makeRevokedLicense();
        $this->assertFalse($license->isLicensed());
    }

    public function test_active_non_revoked_license_is_licensed(): void
    {
        $license = $this->makeActiveLicense();
        $this->assertTrue($license->isLicensed());
    }

    public function test_trial_license_is_licensed_even_without_activation(): void
    {
        $license = $this->makeInactiveLicense(); // installed just now → trial active
        $this->assertTrue($license->isLicensed());
    }

    // ── checkFingerprint ──────────────────────────────────────────────────────

    public function test_check_fingerprint_does_nothing_when_no_fingerprint_stored(): void
    {
        $license = $this->makeActiveLicense(); // no fingerprint stored
        $this->logger->shouldReceive('warning')->never();

        $this->service->checkFingerprint($license);

        $this->expectNotToPerformAssertions();
    }

    public function test_check_fingerprint_logs_warning_on_mismatch(): void
    {
        // Fingerprint set to a value that will never match the current machine
        $license = $this->makeActiveLicenseWithFingerprint('0000000000000000000000000000000000000000000000000000000000000000');

        $this->logger->shouldReceive('warning')
            ->once()
            ->with(Mockery::on(fn(string $msg) => str_contains($msg, 'fingerprint mismatch')), Mockery::type('array'));

        $this->service->checkFingerprint($license);

        // Mockery ->once() enforces the assertion; count it explicitly for PHPUnit
        $this->addToAssertionCount(1);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
        ]);
    }

    private function makeActiveLicense(): License
    {
        return License::fromArray([
            'id'                  => 'bbbbbbbb-0000-0000-0000-bbbbbbbbbbbb',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => $this->service->deriveActivationKey(self::INSTALL_KEY),
            'installed_at'        => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'activated_at'        => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'is_active'           => true,
            'instance_id'         => 'test-host',
            'revoked_at'          => null,
            'machine_fingerprint' => null,
        ]);
    }

    private function makeActiveLicenseWithFingerprint(string $fingerprint): License
    {
        return License::fromArray([
            'id'                  => 'cccccccc-0000-0000-0000-cccccccccccc',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => $this->service->deriveActivationKey(self::INSTALL_KEY),
            'installed_at'        => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'activated_at'        => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'is_active'           => true,
            'instance_id'         => 'test-host',
            'revoked_at'          => null,
            'machine_fingerprint' => $fingerprint,
        ]);
    }

    private function makeRevokedLicense(): License
    {
        return License::fromArray([
            'id'                  => 'dddddddd-0000-0000-0000-dddddddddddd',
            'installation_key'    => self::INSTALL_KEY,
            'activation_key'      => $this->service->deriveActivationKey(self::INSTALL_KEY),
            'installed_at'        => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'activated_at'        => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'is_active'           => true,
            'instance_id'         => 'test-host',
            'revoked_at'          => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'machine_fingerprint' => null,
        ]);
    }
}
