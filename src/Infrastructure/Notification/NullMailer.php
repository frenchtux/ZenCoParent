<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Notification;

use ZenCoParent\Domain\Notification\MailerInterface;

final class NullMailer implements MailerInterface
{
    public function sendWelcome(string $to, string $firstName, string $familyName): void {}

    public function sendInvitation(
        string $to,
        string $inviterName,
        string $familyName,
        string $invitationUrl,
    ): void {}

    public function sendPaymentReceipt(
        string             $to,
        string             $firstName,
        int                $amountCents,
        string             $currency,
        \DateTimeImmutable $date,
    ): void {}

    public function sendLicenseRequestToVendor(
        string $vendorEmail,
        string $installationKey,
        string $adminEmail,
        string $instanceId,
    ): void {}

    public function sendLicensePaymentInstructions(
        string $to,
        string $installationKey,
        string $purchaseUrl,
        string $priceLabel,
    ): void {}
}
