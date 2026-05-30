<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Payment\Payment;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;

/**
 * Community stub — payments do not exist in Community mode.
 * All write methods are no-ops; all read methods return null/empty.
 */
final class SQLitePaymentRepository implements PaymentRepositoryInterface
{
    public function findById(string $id): ?Payment
    {
        return null;
    }

    public function findByStripeSessionId(string $sessionId): ?Payment
    {
        return null;
    }

    public function findByPaypalOrderId(string $orderId): ?Payment
    {
        return null;
    }

    public function findByTenantId(string $tenantId, int $limit = 50): array
    {
        return [];
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        return [];
    }

    public function save(Payment $payment): void {}

    public function updateStatus(string $id, string $status, ?string $stripePaymentIntentId = null, ?\DateTimeImmutable $paidAt = null): void {}
}
