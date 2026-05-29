<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Settings\TenantSettingsService;
use ZenCoParent\Infrastructure\Notification\SmtpMailer;

final class SettingsController
{
    public function __construct(
        private readonly TenantSettingsService $settings,
    ) {}

    /** GET /admin/license/status — check tenant SaaS license */
    public function licenseStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $active   = $this->settings->get($tenantId, 'saas_license_active') === '1';
        $paidAt   = $this->settings->get($tenantId, 'saas_license_paid_at');
        return ApiResponse::success($response, [
            'active'   => $active,
            'paid_at'  => $paidAt,
            'price'    => 150.00,
            'currency' => 'EUR',
        ]);
    }

    /** GET /admin/settings/mail */
    public function getMail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        return ApiResponse::success($response, $this->settings->getMailConfig($tenantId));
    }

    /** PUT /admin/settings/mail */
    public function putMail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();

        $this->settings->setMailConfig($tenantId, $body);

        return ApiResponse::success($response, $this->settings->getMailConfig($tenantId));
    }

    /** POST /admin/settings/mail/test */
    public function testMail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();
        $to       = trim((string) ($body['to'] ?? ''));

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ApiResponse::error($response, "L'adresse email de test est invalide.", 400);
        }

        $mailer = $this->settings->buildMailer($tenantId);
        if ($mailer === null) {
            return ApiResponse::error($response, "Configuration SMTP incomplète (host manquant).", 400);
        }

        try {
            $mailer->sendWelcome($to, 'Test', 'ZenCoParent');
            return ApiResponse::success($response, ['message' => "Email de test envoyé à {$to}."]);
        } catch (\Throwable $e) {
            return ApiResponse::error($response, 'Échec de l\'envoi : ' . $e->getMessage(), 502);
        }
    }
}
