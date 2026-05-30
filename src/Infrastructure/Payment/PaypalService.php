<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Payment;

use ZenCoParent\Domain\Payment\Payment;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;

final class PaypalService
{
    private const SANDBOX_BASE = 'https://api-m.sandbox.paypal.com';
    private const LIVE_BASE    = 'https://api-m.paypal.com';

    private string $baseUrl;

    public function __construct(
        private readonly string                     $clientId,
        private readonly string                     $clientSecret,
        private readonly string                     $mode,
        private readonly string                     $webhookId,
        private readonly string                     $appUrl,
        private readonly PaymentRepositoryInterface $paymentRepo,
    ) {
        $this->baseUrl = $mode === 'live' ? self::LIVE_BASE : self::SANDBOX_BASE;
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    // ── Orders ────────────────────────────────────────────────────────────────

    public function createInstallationKeyOrder(): array
    {
        $orderId    = $this->createOrder(Payment::TYPE_INSTALLATION_KEY, 0, null);
        $approveUrl = $this->getApproveUrl($orderId);

        $payment = Payment::pending(
            type:          Payment::TYPE_INSTALLATION_KEY,
            amountCents:   0,
            currency:      'eur',
            tenantId:      null,
            stripeSessionId: null,
            metadata:      ['type' => Payment::TYPE_INSTALLATION_KEY, 'provider' => 'paypal'],
            paypalOrderId: $orderId,
        );
        $this->paymentRepo->save($payment);

        return ['order_id' => $orderId, 'approve_url' => $approveUrl];
    }

    public function createSaasLicenseOrder(string $tenantId): array
    {
        $orderId    = $this->createOrder(Payment::TYPE_SAAS_LICENSE, 15000, $tenantId);
        $approveUrl = $this->getApproveUrl($orderId);

        $payment = Payment::pending(
            type:          Payment::TYPE_SAAS_LICENSE,
            amountCents:   15000,
            currency:      'eur',
            tenantId:      $tenantId,
            stripeSessionId: null,
            metadata:      ['type' => Payment::TYPE_SAAS_LICENSE, 'tenant_id' => $tenantId, 'provider' => 'paypal'],
            paypalOrderId: $orderId,
        );
        $this->paymentRepo->save($payment);

        return ['order_id' => $orderId, 'approve_url' => $approveUrl];
    }

    public function captureOrder(string $orderId): array
    {
        $result = $this->request('POST', '/v2/checkout/orders/' . $orderId . '/capture');

        $capture     = $result['purchase_units'][0]['payments']['captures'][0] ?? null;
        $amountCents = $capture
            ? (int) round((float) $capture['amount']['value'] * 100)
            : 0;
        $currency    = strtolower($capture['amount']['currency_code'] ?? 'eur');

        return [
            'status'       => $result['status'] ?? '',
            'amount_cents' => $amountCents,
            'currency'     => $currency,
            'order_id'     => $result['id'] ?? $orderId,
        ];
    }

    // ── Webhook verification ──────────────────────────────────────────────────

    public function verifyWebhook(
        string $payload,
        string $transmissionId,
        string $transmissionTime,
        string $certUrl,
        string $authAlgo,
        string $transmissionSig,
    ): bool {
        if ($this->webhookId === '') {
            return false;
        }

        try {
            $result = $this->request('POST', '/v1/notifications/verify-webhook-signature', [
                'transmission_id'   => $transmissionId,
                'transmission_time' => $transmissionTime,
                'cert_url'          => $certUrl,
                'auth_algo'         => $authAlgo,
                'transmission_sig'  => $transmissionSig,
                'webhook_id'        => $this->webhookId,
                'webhook_event'     => json_decode($payload, true),
            ]);
            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function createOrder(string $type, int $amountCents, ?string $tenantId): string
    {
        $returnUrl = $this->appUrl . '/paypal-return.html?type=' . urlencode($type) . '&status=success';
        $cancelUrl = $this->appUrl . '/paypal-return.html?type=' . urlencode($type) . '&status=cancelled';

        $body = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $type . ($tenantId !== null ? ':' . $tenantId : ''),
                'description'  => match ($type) {
                    Payment::TYPE_INSTALLATION_KEY => "Clé d'installation ZenCoParent",
                    Payment::TYPE_SAAS_LICENSE     => 'Licence ZenCoParent SaaS',
                    default                        => 'ZenCoParent',
                },
                'amount'       => [
                    'currency_code' => 'EUR',
                    'value'         => number_format($amountCents / 100, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name'  => 'ZenCoParent',
                'landing_page' => 'LOGIN',
                'user_action'  => 'PAY_NOW',
                'return_url'   => $returnUrl,
                'cancel_url'   => $cancelUrl,
            ],
        ];

        $result = $this->request('POST', '/v2/checkout/orders', $body);
        return $result['id'] ?? throw new \RuntimeException('PayPal order creation failed: missing id');
    }

    private function getApproveUrl(string $orderId): string
    {
        $result = $this->request('GET', '/v2/checkout/orders/' . $orderId);
        foreach ($result['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        // Fallback: build the approve URL manually
        return ($this->mode === 'live'
            ? 'https://www.paypal.com'
            : 'https://www.sandbox.paypal.com')
            . '/checkoutnow?token=' . $orderId;
    }

    private function getAccessToken(): string
    {
        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new \RuntimeException('PayPal auth failed (HTTP ' . $code . ')');
        }

        $data = json_decode((string) $body, true);
        return $data['access_token'] ?? throw new \RuntimeException('PayPal auth: missing access_token');
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $token = $this->getAccessToken();

        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $responseBody, true) ?? [];

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? $data['error_description'] ?? (string) $responseBody;
            throw new \RuntimeException('PayPal API error ' . $httpCode . ': ' . $msg);
        }

        return $data;
    }
}
