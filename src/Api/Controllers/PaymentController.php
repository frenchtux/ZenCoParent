<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Payment\PaypalWebhookHandler;
use ZenCoParent\Application\Payment\StripeWebhookHandler;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Infrastructure\Payment\PaypalService;
use ZenCoParent\Infrastructure\Payment\StripeService;

final class PaymentController
{
    public function __construct(
        private readonly StripeService                   $stripeService,
        private readonly PlanRepositoryInterface         $planRepo,
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly StripeWebhookHandler            $webhookHandler,
        private readonly PaypalService                   $paypalService,
        private readonly PaypalWebhookHandler            $paypalWebhookHandler,
        private readonly PaymentRepositoryInterface      $paymentRepo,
    ) {}

    /** POST /payments/checkout/installation-key — unauthenticated */
    public function checkoutInstallationKey(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $result = $this->stripeService->createInstallationKeySession();
        return ApiResponse::success($response, $result);
    }

    /** POST /payments/checkout/license — admin buys the 150€ SaaS tenant license */
    public function checkoutLicense(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $tenantId = (string) $request->getAttribute('tenantId');
        $result   = $this->stripeService->createSaasLicenseSession($tenantId);
        return ApiResponse::success($response, $result);
    }

    /** POST /payments/checkout/subscription — authenticated family user */
    public function checkoutSubscription(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $tenantId = $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();
        $planId   = trim((string) ($body['plan_id'] ?? ''));
        $interval = trim((string) ($body['interval'] ?? 'monthly'));

        if (!in_array($interval, ['monthly', 'yearly'], true)) {
            return ApiResponse::error($response, "interval doit être 'monthly' ou 'yearly'.", 400);
        }
        if ($planId === '') {
            return ApiResponse::error($response, 'plan_id est requis.', 400);
        }

        $plan = $this->planRepo->findById($planId);
        if ($plan === null) {
            return ApiResponse::error($response, 'Plan introuvable.', 404);
        }

        $stripePriceId = $interval === 'yearly'
            ? $plan->getStripePriceIdYearly()
            : $plan->getStripePriceIdMonthly();

        if ($stripePriceId === null || $stripePriceId === '') {
            return ApiResponse::error($response, 'Ce plan n\'a pas de prix Stripe configuré.', 422);
        }

        $sub = $this->subscriptionRepo->findByTenantId($tenantId);
        $result = $this->stripeService->createSubscriptionSession(
            tenantId:         $tenantId,
            stripePriceId:    $stripePriceId,
            billingInterval:  $interval,
            stripeCustomerId: $sub?->getStripeCustomerId(),
        );

        return ApiResponse::success($response, $result);
    }

    /** GET /billing/status — current subscription status for authenticated tenant */
    public function billingStatus(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $tenantId = (string) $request->getAttribute('tenantId');
        $sub      = $this->subscriptionRepo->findByTenantId($tenantId);

        if ($sub === null) {
            return ApiResponse::success($response, [
                'status'         => 'none',
                'plan'           => null,
                'period_end'     => null,
                'trial_ends_at'  => null,
                'cancel_at'      => null,
                'billing_interval' => null,
            ]);
        }

        $plan = $sub->getPlanId() ? $this->planRepo->findById($sub->getPlanId()) : null;

        return ApiResponse::success($response, [
            'status'           => $sub->getStatus(),
            'plan'             => $plan ? [
                'id'           => $plan->getId(),
                'name'         => $plan->getDisplayName(),
                'price_monthly'=> $plan->getPriceMonthyCents() / 100,
                'price_yearly' => $plan->getPriceYearlyCents() / 100,
            ] : null,
            'period_end'       => $sub->getCurrentPeriodEnd()?->format(\DateTimeInterface::ATOM),
            'trial_ends_at'    => $sub->getTrialEndsAt()?->format(\DateTimeInterface::ATOM),
            'cancel_at'        => $sub->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            'billing_interval' => $sub->getBillingInterval(),
        ]);
    }

    /** GET /payments/portal — redirect to Stripe Customer Portal */
    public function portal(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $tenantId = $request->getAttribute('tenantId');
        $sub = $this->subscriptionRepo->findByTenantId($tenantId);

        if ($sub === null || $sub->getStripeCustomerId() === null) {
            return ApiResponse::error($response, 'Aucun abonnement Stripe trouvé.', 404);
        }

        $url = $this->stripeService->createPortalSession($sub->getStripeCustomerId());
        return ApiResponse::success($response, ['url' => $url]);
    }

    // ── PayPal one-shot endpoints ─────────────────────────────────────────────

    /** POST /payments/checkout/installation-key/paypal — unauthenticated */
    public function checkoutInstallationKeyPaypal(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        if (!$this->paypalService->isConfigured()) {
            return ApiResponse::error($response, 'PayPal non configuré.', 503);
        }
        $result = $this->paypalService->createInstallationKeyOrder();
        return ApiResponse::success($response, $result);
    }

    /** POST /payments/checkout/license/paypal — admin */
    public function checkoutLicensePaypal(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        if (!$this->paypalService->isConfigured()) {
            return ApiResponse::error($response, 'PayPal non configuré.', 503);
        }
        $tenantId = (string) $request->getAttribute('tenantId');
        $result   = $this->paypalService->createSaasLicenseOrder($tenantId);
        return ApiResponse::success($response, $result);
    }

    /** POST /payments/capture/paypal — called after user returns from PayPal */
    public function capturePaypal(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $body    = (array) $request->getParsedBody();
        $orderId = trim((string) ($body['order_id'] ?? ''));

        if ($orderId === '') {
            return ApiResponse::error($response, 'order_id est requis.', 400);
        }

        $payment = $this->paymentRepo->findByPaypalOrderId($orderId);
        if ($payment === null) {
            return ApiResponse::error($response, 'Commande introuvable.', 404);
        }

        try {
            $capture = $this->paypalService->captureOrder($orderId);
        } catch (\Throwable $e) {
            return ApiResponse::error($response, 'Capture PayPal échouée : ' . $e->getMessage(), 502);
        }

        if (($capture['status'] ?? '') !== 'COMPLETED') {
            return ApiResponse::error($response, 'Le paiement PayPal n\'a pas abouti.', 402);
        }

        // Trigger the same business logic as the webhook
        $this->paypalWebhookHandler->handleCaptureCompleted([
            'id'                  => $orderId,
            'supplementary_data'  => ['related_ids' => ['order_id' => $orderId]],
            'amount'              => [
                'value'         => number_format($capture['amount_cents'] / 100, 2, '.', ''),
                'currency_code' => strtoupper($capture['currency']),
            ],
        ]);

        return ApiResponse::success($response, ['status' => 'succeeded', 'order_id' => $orderId]);
    }

    /** POST /payments/webhook/paypal — PayPal webhook (no auth, signature verified) */
    public function webhookPaypal(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $payload = (string) $request->getBody();
        $headers = $request->getHeaders();

        $verified = $this->paypalService->verifyWebhook(
            payload:         $payload,
            transmissionId:  $request->getHeaderLine('PAYPAL-TRANSMISSION-ID'),
            transmissionTime: $request->getHeaderLine('PAYPAL-TRANSMISSION-TIME'),
            certUrl:         $request->getHeaderLine('PAYPAL-CERT-URL'),
            authAlgo:        $request->getHeaderLine('PAYPAL-AUTH-ALGO'),
            transmissionSig: $request->getHeaderLine('PAYPAL-TRANSMISSION-SIG'),
        );

        if (!$verified) {
            $response->getBody()->write(json_encode(['error' => 'Invalid signature']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $event    = json_decode($payload, true) ?? [];
        $type     = $event['event_type'] ?? '';
        $resource = $event['resource']   ?? [];

        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            $this->paypalWebhookHandler->handleCaptureCompleted($resource);
        }

        $response->getBody()->write(json_encode(['received' => true]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    /** POST /payments/webhook — Stripe webhook (no auth, signature verified) */
    public function webhook(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $payload   = (string) $request->getBody();
        $sigHeader = $request->getHeaderLine('Stripe-Signature');

        try {
            $event = $this->stripeService->parseWebhook($payload, $sigHeader);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $response->getBody()->write(json_encode(['error' => 'Invalid signature']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        match ($event->type) {
            'checkout.session.completed'    => $this->webhookHandler->handleCheckoutCompleted($event->data->object),
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->webhookHandler->handleSubscriptionEvent($event->data->object),
            'invoice.payment_succeeded'     => $this->webhookHandler->handleInvoiceSucceeded($event->data->object),
            'invoice.payment_failed'        => $this->webhookHandler->handleInvoiceFailed($event->data->object),
            default                         => null,
        };

        $response->getBody()->write(json_encode(['received' => true]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
}
