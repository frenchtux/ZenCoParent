<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Payment\Payment;
use ZenCoParent\Domain\Payment\PaymentRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLPaymentRepository extends AbstractRepository implements PaymentRepositoryInterface
{
    public function findById(string $id): ?Payment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Payment::fromArray($row) : null;
    }

    public function findByStripeSessionId(string $sessionId): ?Payment
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments WHERE stripe_session_id = :sid'
        );
        $stmt->execute(['sid' => $sessionId]);
        $row = $stmt->fetch();
        return $row !== false ? Payment::fromArray($row) : null;
    }

    public function findByTenantId(string $tenantId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments WHERE tenant_id = :tid ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue('tid', $tenantId);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => Payment::fromArray($r), $stmt->fetchAll());
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, t.name AS tenant_name
             FROM payments p
             LEFT JOIN tenants t ON t.id = p.tenant_id
             ORDER BY p.created_at DESC
             LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => Payment::fromArray($r), $stmt->fetchAll());
    }

    public function save(Payment $payment): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payments
                (id, tenant_id, stripe_payment_intent_id, stripe_invoice_id, stripe_session_id,
                 type, amount_cents, currency, status, metadata, paid_at, created_at)
             VALUES
                (:id, :tenant_id, :stripe_payment_intent_id, :stripe_invoice_id, :stripe_session_id,
                 :type, :amount_cents, :currency, :status, :metadata, :paid_at, NOW())'
        );
        $stmt->execute([
            'id'                       => $payment->getId(),
            'tenant_id'                => $payment->getTenantId(),
            'stripe_payment_intent_id' => $payment->getStripePaymentIntentId(),
            'stripe_invoice_id'        => $payment->getStripeInvoiceId(),
            'stripe_session_id'        => $payment->getStripeSessionId(),
            'type'                     => $payment->getType(),
            'amount_cents'             => $payment->getAmountCents(),
            'currency'                 => $payment->getCurrency(),
            'status'                   => $payment->getStatus(),
            'metadata'                 => json_encode($payment->getMetadata()),
            'paid_at'                  => $payment->getPaidAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateStatus(
        string $id,
        string $status,
        ?string $stripePaymentIntentId = null,
        ?\DateTimeImmutable $paidAt = null,
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE payments SET
                status                    = :status,
                stripe_payment_intent_id  = COALESCE(:pi, stripe_payment_intent_id),
                paid_at                   = COALESCE(:paid_at, paid_at)
             WHERE id = :id'
        );
        $stmt->execute([
            'id'      => $id,
            'status'  => $status,
            'pi'      => $stripePaymentIntentId,
            'paid_at' => $paidAt?->format('Y-m-d H:i:s'),
        ]);
    }
}
