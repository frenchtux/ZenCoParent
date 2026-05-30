<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\License\LicenseService;
use ZenCoParent\Application\Settings\TenantSettingsService;
use ZenCoParent\Domain\Notification\MailerInterface;

final class LicenseController
{
    private const VENDOR_EMAIL  = 'zencoparentapp@gmail.com';
    private const VENDOR_PRICE  = '150,00 EUR';

    private function purchaseUrl(): string
    {
        $base = $this->settings->get(TenantSettingsService::SYSTEM_TENANT, 'app_url')
                ?? rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/');
        return rtrim($base, '/') . '/license-purchase.html';
    }

    public function __construct(
        private LicenseService        $licenseService,
        private TenantSettingsService $settings,
        private MailerInterface       $mailer,
    ) {}

    /** GET /license — current license status (no auth required in SaaS) */
    public function status(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $license = $this->licenseService->getOrCreate();
        return ApiResponse::success($response, $license->toArray());
    }

    /**
     * POST /license/request — demande de licence manuelle
     * Envoie un email au vendeur + instructions PayPal à l'admin.
     * Requiert que le SMTP soit configuré.
     */
    public function request(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body       = (array) $request->getParsedBody();
        $adminEmail = trim((string) ($body['admin_email'] ?? ''));

        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return ApiResponse::error($response, 'Une adresse email valide est requise.', 400);
        }

        // Vérifier que le SMTP est configuré (settings tenant ou env)
        $tenantId = (string) $request->getAttribute('tenantId');
        $dbHost   = $tenantId !== '' ? $this->settings->get($tenantId, 'mail_host') : null;
        $envHost  = $_ENV['MAIL_HOST'] ?? '';

        if (($dbHost === null || $dbHost === '') && $envHost === '') {
            return ApiResponse::error(
                $response,
                'La configuration SMTP est requise pour envoyer la demande de licence. '
                . 'Configurez-la dans Paramètres → Email avant de continuer.',
                422,
            );
        }

        $license = $this->licenseService->getOrCreate();

        try {
            // Email au vendeur
            $this->mailer->sendLicenseRequestToVendor(
                vendorEmail:     self::VENDOR_EMAIL,
                installationKey: $license->getInstallationKey(),
                adminEmail:      $adminEmail,
                instanceId:      $license->getInstanceId() ?? 'unknown',
            );

            // Instructions de paiement à l'admin
            $this->mailer->sendLicensePaymentInstructions(
                to:              $adminEmail,
                installationKey: $license->getInstallationKey(),
                purchaseUrl:     $this->purchaseUrl(),
                priceLabel:      self::VENDOR_PRICE,
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                $response,
                "Échec de l'envoi des emails : " . $e->getMessage()
                . ' — Vérifiez votre configuration SMTP.',
                502,
            );
        }

        return ApiResponse::success($response, [
            'message'         => 'Demande envoyée. Vous allez recevoir les instructions de paiement par email.',
            'installation_key' => $license->getInstallationKey(),
        ]);
    }

    /** POST /license/activate — submit activation key */
    public function activate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body           = (array) $request->getParsedBody();
        $activationKey  = trim((string) ($body['activation_key'] ?? ''));

        if ($activationKey === '') {
            return ApiResponse::error($response, "La clé d'activation est requise.", 400);
        }

        $ok = $this->licenseService->activate($activationKey);

        if (!$ok) {
            return ApiResponse::error($response, "Clé d'activation invalide.", 422);
        }

        $license = $this->licenseService->getOrCreate();
        return ApiResponse::success($response, $license->toArray());
    }
}
