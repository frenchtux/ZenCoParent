<?php
declare(strict_types=1);

namespace ZenCoParent\Application\License;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ZenCoParent\Domain\License\License;
use ZenCoParent\Domain\License\LicenseRepositoryInterface;

final class LicenseService
{
    /**
     * Ed25519 public key (hex, 32 bytes).
     * The matching private key lives only in the offline license generator — never on this server.
     * To rotate: generate a new keypair with `python scripts/generate_license.py --keygen`,
     * update this constant, and redeploy.
     */
    private const PUBLIC_KEY_HEX = '7040c3ceb3d4690974df3a2b396b61377998e6db9fea95fb87cd565e2f877fc2';

    public function __construct(
        private LicenseRepositoryInterface $repo,
        private string                     $publicKeyHex = self::PUBLIC_KEY_HEX,
        private LoggerInterface            $logger       = new NullLogger(),
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
     * Validate and apply a v2 Ed25519-signed license token.
     *
     * Token format: base64url(json_payload) . "." . base64url(ed25519_signature)
     *
     * Payload fields (JSON):
     *   v                : 2
     *   installation_key : must match the stored installation key
     *   customer_email   : informational
     *   issued_at        : ISO 8601
     *   expires_at       : ISO 8601 or null
     *
     * Returns true on success, false if the token is invalid, expired, or for a different installation.
     */
    public function activate(string $token): bool
    {
        $parts = explode('.', trim($token), 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$payloadB64, $sigB64] = $parts;

        $payloadJson = self::base64urlDecode($payloadB64);
        $signature   = self::base64urlDecode($sigB64);

        if ($payloadJson === false || $signature === false) {
            return false;
        }

        // Verify Ed25519 signature
        try {
            $publicKey = sodium_hex2bin($this->publicKeyHex);
            $valid     = sodium_crypto_sign_verify_detached($signature, $payloadJson, $publicKey);
        } catch (\Throwable) {
            return false;
        }

        if (!$valid) {
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || ($payload['v'] ?? 0) !== 2) {
            return false;
        }

        $license = $this->getOrCreate();

        // Installation key must match this specific instance
        if (($payload['installation_key'] ?? '') !== $license->getInstallationKey()) {
            return false;
        }

        // Check expiry if set
        if (!empty($payload['expires_at'])) {
            try {
                $expiresAt = new \DateTimeImmutable($payload['expires_at']);
                if ($expiresAt < new \DateTimeImmutable()) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        $fingerprint = $this->calculateMachineFingerprint();
        $expiresAt   = !empty($payload['expires_at']) ? new \DateTimeImmutable($payload['expires_at']) : null;
        $activated   = $license->withActivation(
            token:         trim($token),
            fingerprint:   $fingerprint,
            customerEmail: $payload['customer_email'] ?? null,
            expiresAt:     $expiresAt,
        );

        $this->repo->update($activated);
        return true;
    }

    /**
     * Revoke the current installation license.
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
     * Logs a warning on mismatch (detection only — never blocks).
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

    // ── Private helpers ───────────────────────────────────────────────────────

    /** ZNCO-XXXX-XXXX-XXXX-XXXX-XXXX  (10 random bytes → 20 uppercase hex chars) */
    private function generateInstallationKey(): string
    {
        $hex = strtoupper(bin2hex(random_bytes(10)));
        return 'ZNCO-' . implode('-', str_split($hex, 4));
    }

    /** SHA-256 of: hostname + install path + first available MAC address. */
    private function calculateMachineFingerprint(): string
    {
        $hostname = gethostname() ?: 'unknown';
        $path     = realpath(__DIR__ . '/../../../') ?: __DIR__;
        $mac      = $this->getFirstMacAddress();
        return hash('sha256', implode('|', [$hostname, $path, $mac]));
    }

    private function getFirstMacAddress(): string
    {
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
        $output = @shell_exec("ip link show 2>/dev/null | grep -oP '(?<=ether )[\w:]+' | head -1");
        if ($output && trim($output) !== '') {
            return trim($output);
        }
        return 'unknown';
    }

    private static function base64urlDecode(string $input): string|false
    {
        $padded = str_pad(strtr($input, '-_', '+/'), strlen($input) + (4 - strlen($input) % 4) % 4, '=');
        return base64_decode($padded, strict: true);
    }
}
