<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Payment;

use Psr\Log\LoggerInterface;
use ZenCoParent\Application\Settings\TenantSettingsService;
use ZenCoParent\Application\Subscription\SubscriptionService;
use ZenCoParent\Domain\Notification\MailerInterface;
use ZenCoParent\Domain\Payment\Payment;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Domain\Subscription\SubscriptionRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;

final class StripeWebhookHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface      $paymentRepo,
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly PlanRepositoryInterface         $planRepo,
        private readonly SubscriptionService             $subscriptionService,
        private readonly UserRepositoryInterface         $userRepo,
        private readonly MailerInterface                 $mailer,
        private readonly LoggerInterface                 $logger,
        private readonly ?TenantSettingsService          $tenantSettings = null,
    ) {}

    public function handleCheckoutCompleted(object $session): void
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

        if ($type === Payment::TYPE_SAAS_LICENSE) {
            $tenantId = $session->metadata->tenant_id ?? null;
            $payment  = $this->paymentRepo->findByStripeSessionId($session->id);
            if ($payment) {
                $this->paymentRepo->updateStatus(
                    $payment->getId(),
                    Payment::STATUS_SUCCEEDED,
                    $session->payment_intent ?? null,
                    new \DateTimeImmutable(),
                );
            }
            if ($tenantId !== null && $this->tenantSettings !== null) {
                $this->tenantSettings->set($tenantId, 'saas_license_active', '1');
                $this->tenantSettings->set($tenantId, 'saas_license_paid_at', (new \DateTimeImmutable())->format('Y-m-d H:i:s'));
            }
            $this->logger->info('SaaS license activated via Stripe', ['tenant_id' => $tenantId]);
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

            try {
                $stripeSub = \Stripe\Subscription::retrieve($stripeSubId);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $this->logger->error('Stripe subscription retrieve failed in webhook', [
                    'stripe_sub_id' => $stripeSubId,
                    'error'         => $e->getMessage(),
                ]);
                return;
            }

            $item    = $stripeSub->items->data[0] ?? null;
            $priceId = $item?->price->id;

            $plan = null;
            foreach ($this->planRepo->findAll() as $p) {
                if ($p->getStripePriceIdMonthly() === $priceId
                    || $p->getStripePriceIdYearly() === $priceId) {
                    $plan = $p;
                    break;
                }
            }

            if ($plan === null) {
                $this->logger->warning('No plan found for Stripe price in webhook', ['price_id' => $priceId]);
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

    public function handleSubscriptionEvent(object $stripeSub): void
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

    public function handleInvoiceSucceeded(object $invoice): void
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

        // Send receipt email to the tenant admin
        if ($sub?->getTenantId() !== null) {
            try {
                $users = $this->userRepo->findByTenantId($sub->getTenantId());
                $admin = null;
                foreach ($users as $u) {
                    if ($u->getRole()->value === 'admin') {
                        $admin = $u;
                        break;
                    }
                }
                if ($admin !== null) {
                    $this->mailer->sendPaymentReceipt(
                        to:          $admin->getEmail(),
                        firstName:   $admin->getFirstName(),
                        amountCents: (int) $invoice->amount_paid,
                        currency:    $invoice->currency,
                        date:        new \DateTimeImmutable(),
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Could not send payment receipt email', ['error' => $e->getMessage()]);
            }
        }
    }

    public function handleInvoiceFailed(object $invoice): void
    {
        $existing = $this->paymentRepo->findByStripeSessionId($invoice->id);
        if ($existing !== null) {
            $this->paymentRepo->updateStatus($existing->getId(), Payment::STATUS_FAILED);
        }
    }
}
