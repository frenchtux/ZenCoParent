<?php
declare(strict_types=1);

namespace ZenCoParent\Application\License;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ZenCoParent\Domain\License\License;
use ZenCoParent\Domain\License\LicenseRepositoryInterface;

final class LicenseService
{
    public function __construct(
        private LicenseRepositoryInterface $repo,
        private string                     $masterKey,
        private LoggerInterface            $logger = new NullLogger(),
    ) {}

    /**
     * Return the current installation license, creating it on first boot.
     */
    public function getOrCreate(): License
    {
        $license = $this->repo->get();
        if ($license === null) {
            $license = License::create(
                installationKey: $this->generateInstallationKey(),
                instanceId:      gethostname() ?: 'unknown',
            );
            $this->repo->save($license);
        }
        return $license;
    }

    /**
     * Validate and apply an activation key.
     * Stores the machine fingerprint on success.
     * Returns true on success, false if the key is wrong.
     */
    public function activate(string $activationKey): bool
    {
        $license  = $this->getOrCreate();
        $expected = $this->deriveActivationKey($license->getInstallationKey());

        if (!hash_equals($expected, strtoupper(trim($activationKey)))) {
            return false;
        }

        $fingerprint = $this->calculateMachineFingerprint();
        $activated   = $license->withActivation(strtoupper(trim($activationKey)), $fingerprint);
        $this->repo->update($activated);
        return true;
    }

    /**
     * Revoke the current installation license.
     * Returns false if no license exists or it is already revoked.
     */
    public function revoke(): bool
    {
        $license = $this->repo->get();
        if ($license === null || $license->isRevoked()) {
            return false;
        }
        $this->repo->update($license->withRevocation());
        return true;
    }

    /**
     * Check whether the current machine fingerprint matches the stored one.
     * Logs a warning if a mismatch is detected (possible cloned installation).
     * Never blocks — detection only.
     */
    public function checkFingerprint(License $license): void
    {
        $stored = $license->getMachineFingerprint();
        if ($stored === null) {
            return;
        }
        $current = $this->calculateMachineFingerprint();
        if (!hash_equals($stored, $current)) {
            $this->logger->warning(
                'License fingerprint mismatch — possible cloned installation.',
                [
                    'installation_key' => $license->getInstallationKey(),
                    'instance_id'      => $license->getInstanceId(),
                ]
            );
        }
    }

    /**
     * Derive the activation key for a given installation key.
     * The developer uses this offline to generate keys for customers.
     *
     * Format: ACT-XXXX-XXXX-XXXX-XXXX-XXXX  (20 uppercase hex chars, grouped)
     */
    public function deriveActivationKey(string $installationKey): string
    {
        $hmac  = strtoupper(hash_hmac('sha256', $installationKey, $this->masterKey));
        $chars = substr($hmac, 0, 20);
        return 'ACT-' . implode('-', str_split($chars, 4));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Format: ZNCO-XXXX-XXXX-XXXX-XXXX-XXXX  (20 uppercase hex chars, grouped)
     */
    private function generateInstallationKey(): string
    {
        $hex = strtoupper(bin2hex(random_bytes(10)));
        return 'ZNCO-' . implode('-', str_split($hex, 4));
    }

    /**
     * SHA-256 of: hostname + installation path + first available MAC address.
     */
    private function calculateMachineFingerprint(): string
    {
        $hostname = gethostname() ?: 'unknown';
        $path     = realpath(__DIR__ . '/../../../') ?: __DIR__;
        $mac      = $this->getFirstMacAddress();
        return hash('sha256', implode('|', [$hostname, $path, $mac]));
    }

    private function getFirstMacAddress(): string
    {
        // Prefer Linux /sys/class/net (works in Docker without shell_exec)
        $sysNet = '/sys/class/net';
        if (is_dir($sysNet)) {
            foreach (scandir($sysNet) ?: [] as $iface) {
                if ($iface === '.' || $iface === '..' || $iface === 'lo') {
                    continue;
                }
                $addr = @file_get_contents("{$sysNet}/{$iface}/address");
                if ($addr && trim($addr) !== '00:00:00:00:00:00') {
                    return trim($addr);
                }
            }
        }
        // Fallback: ip command (Linux/Docker)
        $output = @shell_exec("ip link show 2>/dev/null | grep -oP '(?<=ether )[\w:]+' | head -1");
        if ($output && trim($output) !== '') {
            return trim($output);
        }
        return 'unknown';
    }
}
