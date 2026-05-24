<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Domain\Payment\Payment;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Infrastructure\Payment\StripeService;
use ZenCoParent\Application\Subscription\SubscriptionService;

final class PaymentController
{
    public function __construct(
        private readonly StripeService                   $stripeService,
        private readonly PlanRepositoryInterface         $planRepo,
        private readonly SubscriptionService             $subscriptionService,
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
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
            'checkout.session.completed'         => $this->handleCheckoutCompleted($event->data->object),
            'customer.subscription.updated',
            'customer.subscription.deleted'      => $this->handleSubscriptionEvent($event->data->object),
            'invoice.payment_succeeded'          => $this->handleInvoiceSucceeded($event->data->object),
            'invoice.payment_failed'             => $this->handleInvoiceFailed($event->data->object),
            default                              => null,
        };

        $response->getBody()->write(json_encode(['received' => true]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    private function handleCheckoutCompleted(object $session): void
    {
        $type = $session->metadata->type ?? '';

        if ($type === Payment::TYPE_INSTALLATION_KEY) {
            $payment = $this->paymentRepo->findByStripeSessionId($session->id);
            if ($payment) {
                $this->paymentRepo->updateStatus(
                    $payment->getId(),
                    Payment::STATUS_SUCCEEDED,
                    $session->payment_intent ?? null,
                    new \DateTimeImmutable(),
                );
            }
            return;
        }

        if ($type === Payment::TYPE_SUBSCRIPTION) {
            $tenantId        = $session->metadata->tenant_id ?? null;
            $billingInterval = $session->metadata->billing_interval ?? 'monthly';
            $stripeSubId     = $session->subscription ?? null;
            $customerId      = $session->customer ?? null;

            if ($tenantId === null || $stripeSubId === null) {
                return;
            }

            // Fetch Stripe subscription details to get price and period
            $stripeSub = \Stripe\Subscription::retrieve($stripeSubId);
            $item      = $stripeSub->items->data[0] ?? null;
            $priceId   = $item?->price->id;

            // Resolve plan from Stripe price ID
            $allPlans = $this->planRepo->findAll();
            $plan     = null;
            foreach ($allPlans as $p) {
                if ($p->getStripePriceIdMonthly() === $priceId
                    || $p->getStripePriceIdYearly() === $priceId) {
                    $plan = $p;
                    break;
                }
            }

            if ($plan === null) {
                return;
            }

            $this->subscriptionService->syncFromStripe(
                stripeSubscriptionId: $stripeSubId,
                stripeCustomerId:     $customerId,
                tenantId:             $tenantId,
                planId:               $plan->getId(),
                status:               'active',
                billingInterval:      $billingInterval,
                periodStart:          new \DateTimeImmutable('@' . $stripeSub->current_period_start),
                periodEnd:            new \DateTimeImmutable('@' . $stripeSub->current_period_end),
            );
        }
    }

    private function handleSubscriptionEvent(object $stripeSub): void
    {
        $sub = $this->subscriptionRepo->findByStripeSubscriptionId($stripeSub->id);
        if ($sub === null) {
            return;
        }

        $stripeStatus = match ($stripeSub->status) {
            'active'   => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            default    => 'expired',
        };

        $this->subscriptionRepo->update($sub->getId(), [
            'status'               => $stripeStatus,
            'current_period_start' => new \DateTimeImmutable('@' . $stripeSub->current_period_start),
            'current_period_end'   => new \DateTimeImmutable('@' . $stripeSub->current_period_end),
            'cancelled_at'         => $stripeSub->canceled_at
                ? new \DateTimeImmutable('@' . $stripeSub->canceled_at)
                : null,
        ]);
    }

    private function handleInvoiceSucceeded(object $invoice): void
    {
        $existing = $this->paymentRepo->findByStripeSessionId($invoice->id);
        if ($existing !== null) {
            $this->paymentRepo->updateStatus(
                $existing->getId(),
                Payment::STATUS_SUCCEEDED,
                $invoice->payment_intent ?? null,
                new \DateTimeImmutable(),
            );
            return;
        }

        // Record new payment for subscription renewal
        $sub = $invoice->subscription
            ? $this->subscriptionRepo->findByStripeSubscriptionId($invoice->subscription)
            : null;

        $payment = Payment::pending(
            type:            Payment::TYPE_SUBSCRIPTION,
            amountCents:     (int) $invoice->amount_paid,
            currency:        $invoice->currency,
            tenantId:        $sub?->getTenantId(),
            stripeSessionId: $invoice->id,
            metadata:        ['stripe_invoice_id' => $invoice->id],
        );
        $this->paymentRepo->save($payment);
        $this->paymentRepo->updateStatus(
            $payment->getId(),
            Payment::STATUS_SUCCEEDED,
            $invoice->payment_intent ?? null,
            new \DateTimeImmutable(),
        );
    }

    private function handleInvoiceFailed(object $invoice): void
    {
        $existing = $this->paymentRepo->findByStripeSessionId($invoice->id);
        if ($existing !== null) {
            $this->paymentRepo->updateStatus($existing->getId(), Payment::STATUS_FAILED);
        }
    }
}
