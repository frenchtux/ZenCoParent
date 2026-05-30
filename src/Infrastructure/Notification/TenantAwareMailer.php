<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Notification;

use ZenCoParent\Application\Settings\TenantSettingsService;
use ZenCoParent\Domain\Notification\MailerInterface;

/**
 * Mailer that resolves SMTP config per-tenant from tenant_settings,
 * falling back to environment variables when no tenant config is set.
 *
 * This allows each tenant to configure their own SMTP server via the
 * admin UI without requiring a container restart.
 */
final class TenantAwareMailer implements MailerInterface
{
    public function __construct(
        private readonly TenantSettingsService $settings,
        private readonly MailerInterface        $fallback,
    ) {}

    public function sendWelcome(string $to, string $firstName, string $familyName): void
    {
        $this->resolveMailer($to)->sendWelcome($to, $firstName, $familyName);
    }

    public function sendInvitation(string $to, string $inviterName, string $familyName, string $invitationUrl): void
    {
        $this->resolveMailer($to)->sendInvitation($to, $inviterName, $familyName, $invitationUrl);
    }

    public function sendPaymentReceipt(string $to, string $firstName, int $amountCents, string $currency, \DateTimeImmutable $date): void
    {
        $this->resolveMailer($to)->sendPaymentReceipt($to, $firstName, $amountCents, $currency, $date);
    }

    public function sendLicenseRequestToVendor(string $vendorEmail, string $installationKey, string $adminEmail, string $instanceId): void
    {
        $this->resolveMailer($vendorEmail)->sendLicenseRequestToVendor($vendorEmail, $installationKey, $adminEmail, $instanceId);
    }

    public function sendLicensePaymentInstructions(string $to, string $installationKey, string $paypalEmail, string $priceLabel): void
    {
        $this->resolveMailer($to)->sendLicensePaymentInstructions($to, $installationKey, $paypalEmail, $priceLabel);
    }

    /**
     * Attempt to build an SMTP mailer from the current request's tenant settings.
     * Falls back to the env-based mailer if no tenant context or no DB config.
     *
     * The tenant ID is injected as a request attribute in $GLOBALS['_ZENCO_TENANT_ID']
     * by a lightweight middleware — this avoids polluting every handler signature.
     */
    private function resolveMailer(string $to): MailerInterface
    {
        $tenantId = $GLOBALS['_ZENCO_TENANT_ID'] ?? null;
        if ($tenantId === null) {
            return $this->fallback;
        }

        try {
            $mailer = $this->settings->buildMailer($tenantId);
            return $mailer ?? $this->fallback;
        } catch (\Throwable) {
            return $this->fallback;
        }
    }
}
