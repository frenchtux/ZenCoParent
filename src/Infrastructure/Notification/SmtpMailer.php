<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Notification;

use PHPMailer\PHPMailer\PHPMailer;
use ZenCoParent\Domain\Notification\MailerInterface;

final class SmtpMailer implements MailerInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $encryption,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {}

    public function sendWelcome(string $to, string $firstName, string $familyName): void
    {
        $name   = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $family = htmlspecialchars($familyName, ENT_QUOTES, 'UTF-8');

        $this->send(
            to:      $to,
            subject: 'Bienvenue sur ZenCoParent !',
            html:    $this->layout(
                title:   'Bienvenue !',
                heading: "Bienvenue sur ZenCoParent, {$name} !",
                body:    "<p>Votre espace famille <strong>« {$family} »</strong> est prêt.</p>
                          <p>Vous pouvez maintenant inviter votre co-parent, ajouter vos enfants
                          et gérer votre calendrier partagé.</p>",
            ),
            text: "Bonjour {$firstName}, votre espace famille « {$familyName} » est prêt sur ZenCoParent.",
        );
    }

    public function sendInvitation(
        string $to,
        string $inviterName,
        string $familyName,
        string $invitationUrl,
    ): void {
        $inviter = htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8');
        $family  = htmlspecialchars($familyName,  ENT_QUOTES, 'UTF-8');
        $url     = htmlspecialchars($invitationUrl, ENT_QUOTES, 'UTF-8');

        $this->send(
            to:      $to,
            subject: "{$inviterName} vous invite à rejoindre ZenCoParent",
            html:    $this->layout(
                title:   'Invitation',
                heading: "Vous êtes invité(e) par {$inviter}",
                body:    "<p><strong>{$inviter}</strong> vous invite à rejoindre la famille
                          <strong>« {$family} »</strong> sur ZenCoParent.</p>
                          <p style=\"margin-top:24px;\">
                            <a href=\"{$url}\" style=\"background:#6366f1;color:#fff;padding:12px 24px;
                               border-radius:8px;text-decoration:none;font-weight:600\">
                              Accepter l'invitation
                            </a>
                          </p>
                          <p style=\"margin-top:16px;color:#6b7280;font-size:.85em\">
                            Lien valable 7 jours. Si le bouton ne fonctionne pas :
                            <a href=\"{$url}\">{$url}</a>
                          </p>",
            ),
            text: "{$inviterName} vous invite à rejoindre « {$familyName} » sur ZenCoParent : {$invitationUrl}",
        );
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function send(string $to, string $subject, string $html, string $text): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->username;
        $mail->Password   = $this->password;
        $mail->SMTPSecure = $this->encryption === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->port;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($this->fromAddress, $this->fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
    }

    private function layout(string $title, string $heading, string $body): string
    {
        $title   = htmlspecialchars($title,   ENT_QUOTES, 'UTF-8');
        $heading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
          <meta charset="UTF-8">
          <title>{$title}</title>
        </head>
        <body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
                     background:#f3f4f6;margin:0;padding:40px 20px;">
          <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;
                      padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
            <p style="color:#6366f1;font-weight:700;font-size:.85em;
                      letter-spacing:.05em;margin-top:0">ZENCOPARENT</p>
            <h1 style="color:#111827;font-size:1.5rem;margin:8px 0 24px">{$heading}</h1>
            {$body}
            <hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0">
            <p style="color:#9ca3af;font-size:.8em;margin:0">
              © ZenCoParent — Co-parentalité sereine
            </p>
          </div>
        </body>
        </html>
        HTML;
    }
}
