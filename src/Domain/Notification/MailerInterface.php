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

}
