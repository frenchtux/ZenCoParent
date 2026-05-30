<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Notification;

interface MailerInterface
{
    public function sendWelcome(string $to, string $firstName, string $familyName): void;

    public function sendInvitation(
        string $to,
        string $inviterName,
        string $familyName,
        string $invitationUrl,
    ): void;

    public function sendPaymentReceipt(
        string             $to,
        string             $firstName,
        int                $amountCents,
        string             $currency,
        \DateTimeImmutable $date,
    ): void;

    /** Notifie le vendeur qu'une demande de licence a été reçue. */
    public function sendLicenseRequestToVendor(
        string $vendorEmail,
        string $installationKey,
        string $adminEmail,
        string $instanceId,
    ): void;

    /** Envoie les instructions de paiement à l'admin qui demande la licence. */
    public function sendLicensePaymentInstructions(
        string $to,
        string $installationKey,
        string $purchaseUrl,
        string $priceLabel,
    ): void;
}
