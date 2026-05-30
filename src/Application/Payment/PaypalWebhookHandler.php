<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Payment;

use Psr\Log\LoggerInterface;
use ZenCoParent\Application\Settings\TenantSettingsService;
use ZenCoParent\Domain\Payment\Payment;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;

final class PaypalWebhookHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepo,
        private readonly LoggerInterface            $logger,
        private readonly ?TenantSettingsService     $tenantSettings = null,
    ) {}

    public function handleCaptureCompleted(array $resource): void
    {
        // For PAYMENT.CAPTURE.COMPLETED, the resource is the capture object.
        // The originating order_id is in supplementary_data.related_ids.order_id.
        $orderId = $resource['supplementary_data']['related_ids']['order_id']
            ?? $resource['id'] // fallback: use capture id if order ref is missing
            ?? null;

        if ($orderId === null) {
            $this->logger->warning('PayPal webhook: no order_id in capture event', ['resource' => $resource]);
            return;
        }

        $payment = $this->paymentRepo->findByPaypalOrderId($orderId);

        if ($payment === null) {
            $this->logger->warning('PayPal webhook: no payment found for order', ['order_id' => $orderId]);
            return;
        }

        if ($payment->getStatus() === Payment::STATUS_SUCCEEDED) {
            return; // idempotent — already processed
        }

        $this->paymentRepo->updateStatus(
            $payment->getId(),
            Payment::STATUS_SUCCEEDED,
            paidAt: new \DateTimeImmutable(),
        );

        if ($payment->getType() === Payment::TYPE_SAAS_LICENSE
            && $payment->getTenantId() !== null
            && $this->tenantSettings !== null
        ) {
            $this->tenantSettings->set($payment->getTenantId(), 'saas_license_active', '1');
            $this->tenantSettings->set(
                $payment->getTenantId(),
                'saas_license_paid_at',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            );
            $this->logger->info('SaaS license activated via PayPal', [
                'tenant_id' => $payment->getTenantId(),
                'order_id'  => $orderId,
            ]);
        }
    }
}
