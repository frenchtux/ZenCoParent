<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Payment\StripeWebhookHandler;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Infrastructure\Payment\StripeService;

final class PaymentController
{
    public function __construct(
        private readonly StripeService                   $stripeService,
        private readonly PlanRepositoryInterface         $planRepo,
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly StripeWebhookHandler            $webhookHandler,
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
