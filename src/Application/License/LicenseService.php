<?php
declare(strict_types=1);

namespace ZenCoParent\Application\License;

use ZenCoParent\Domain\License\License;
use ZenCoParent\Domain\License\LicenseRepositoryInterface;

final class LicenseService
{
    public function __construct(
        private LicenseRepositoryInterface $repo,
        private string                     $masterKey,
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
     * Returns true on success, false if the key is wrong.
     */
    public function activate(string $activationKey): bool
    {
        $license  = $this->getOrCreate();
        $expected = $this->deriveActivationKey($license->getInstallationKey());

        if (!hash_equals($expected, strtoupper(trim($activationKey)))) {
            return false;
        }

        $activated = $license->withActivation(strtoupper(trim($activationKey)));
        $this->repo->update($activated);
        return true;
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

    /**
     * Format: ZNCO-XXXX-XXXX-XXXX-XXXX-XXXX  (20 uppercase hex chars, grouped)
     */
    private function generateInstallationKey(): string
    {
        $hex = strtoupper(bin2hex(random_bytes(10)));
        return 'ZNCO-' . implode('-', str_split($hex, 4));
    }
}
