<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Payment;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Stripe\Event;
use ZenCoParent\Domain\Payment\Payment;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;

final class StripeService
{
    public function __construct(
        private readonly string                    $secretKey,
        private readonly string                    $webhookSecret,
        private readonly string                    $installationKeyPriceId,
        private readonly string                    $appUrl,
        private readonly PaymentRepositoryInterface $paymentRepo,
    ) {
        Stripe::setApiKey($this->secretKey);
    }

    /**
     * Create a Checkout Session so a SaaS operator can purchase an installation key.
     * Returns the session URL to redirect the user to.
     */
    public function createInstallationKeySession(): array
    {
        $session = Session::create([
            'mode'        => 'payment',
            'line_items'  => [[
                'price'    => $this->installationKeyPriceId,
                'quantity' => 1,
            ]],
            'success_url' => $this->appUrl . '/license.html?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->appUrl . '/license.html?checkout=cancelled',
            'metadata'    => ['type' => Payment::TYPE_INSTALLATION_KEY],
        ]);

        $payment = Payment::pending(
            type:            Payment::TYPE_INSTALLATION_KEY,
            amountCents:     0,
            currency:        'eur',
            tenantId:        null,
            stripeSessionId: $session->id,
            metadata:        ['type' => Payment::TYPE_INSTALLATION_KEY],
        );
        $this->paymentRepo->save($payment);

        return ['url' => $session->url, 'session_id' => $session->id];
    }

    /**
     * Create a Checkout Session for a family subscription.
     */
    public function createSubscriptionSession(
        string  $tenantId,
        string  $stripePriceId,
        string  $billingInterval,
        ?string $stripeCustomerId,
    ): array {
        $params = [
            'mode'        => 'subscription',
            'line_items'  => [[
                'price'    => $stripePriceId,
                'quantity' => 1,
            ]],
            'success_url' => $this->appUrl . '/dashboard.html?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->appUrl . '/abonnement.html?checkout=cancelled',
            'metadata'    => [
                'type'             => Payment::TYPE_SUBSCRIPTION,
                'tenant_id'        => $tenantId,
                'billing_interval' => $billingInterval,
            ],
        ];
        if ($stripeCustomerId !== null) {
            $params['customer'] = $stripeCustomerId;
        }

        $session = Session::create($params);

        return ['url' => $session->url, 'session_id' => $session->id];
    }

    /**
     * Create a Stripe Customer Portal session so a family can manage their subscription.
     */
    public function createPortalSession(string $stripeCustomerId): string
    {
        $session = \Stripe\BillingPortal\Session::create([
            'customer'   => $stripeCustomerId,
            'return_url' => $this->appUrl . '/abonnement.html',
        ]);
        return $session->url;
    }

    /**
     * Verify and parse an incoming Stripe webhook.
     * Throws \Stripe\Exception\SignatureVerificationException on bad signature.
     */
    public function parseWebhook(string $payload, string $sigHeader): Event
    {
        return Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }
}
