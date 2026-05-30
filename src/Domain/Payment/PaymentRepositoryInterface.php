<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Payment;

interface PaymentRepositoryInterface
{
    public function findById(string $id): ?Payment;

    public function findByStripeSessionId(string $sessionId): ?Payment;

    public function findByPaypalOrderId(string $orderId): ?Payment;

    /** @return Payment[] */
    public function findByTenantId(string $tenantId, int $limit = 50): array;

    /** @return Payment[] */
    public function findAll(int $limit = 100, int $offset = 0): array;

    public function save(Payment $payment): void;

    public function updateStatus(string $id, string $status, ?string $stripePaymentIntentId = null, ?\DateTimeImmutable $paidAt = null): void;
}
