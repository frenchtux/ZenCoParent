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

    // ── OAuth ─────────────────────────────────────────────────────────────────

    /** GET /admin/settings/oauth */
    public function getOAuth(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success($response, $this->settings->getOAuthConfig());
    }

    /** PUT /admin/settings/oauth */
    public function putOAuth(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->settings->setOAuthConfig((array) $request->getParsedBody());
        return ApiResponse::success($response, $this->settings->getOAuthConfig());
    }

    // ── App ───────────────────────────────────────────────────────────────────

    /** GET /admin/settings/app */
    public function getApp(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success($response, $this->settings->getAppConfig());
    }

    /** PUT /admin/settings/app */
    public function putApp(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->settings->setAppConfig((array) $request->getParsedBody());
        return ApiResponse::success($response, $this->settings->getAppConfig());
    }

    // ── Security ──────────────────────────────────────────────────────────────

    /** GET /admin/settings/security */
    public function getSecurity(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success($response, $this->settings->getSecurityConfig());
    }

    /** PUT /admin/settings/security */
    public function putSecurity(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        foreach (['jwt_expiry', 'jwt_refresh_expiry', 'rate_limit_requests', 'rate_limit_window'] as $intKey) {
            if (isset($body[$intKey]) && $body[$intKey] !== null) {
                $v = (int) $body[$intKey];
                if ($v <= 0) {
                    return ApiResponse::error($response, "{$intKey} doit être un entier positif.", 400);
                }
                $body[$intKey] = (string) $v;
            }
        }

        $this->settings->setSecurityConfig($body);
        return ApiResponse::success($response, $this->settings->getSecurityConfig());
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
