<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Payment;

final class Payment
{
    public const TYPE_INSTALLATION_KEY = 'installation_key';
    public const TYPE_SUBSCRIPTION     = 'subscription';
    public const TYPE_SAAS_LICENSE     = 'saas_license';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REFUNDED  = 'refunded';

    public function __construct(
        private readonly string              $id,
        private readonly ?string             $tenantId,
        private readonly ?string             $stripePaymentIntentId,
        private readonly ?string             $stripeInvoiceId,
        private readonly ?string             $stripeSessionId,
        private readonly string              $type,
        private readonly int                 $amountCents,
        private readonly string              $currency,
        private readonly string              $status,
        private readonly array               $metadata,
        private readonly ?\DateTimeImmutable $paidAt,
        private readonly \DateTimeImmutable  $createdAt,
    ) {}

    public static function pending(
        string  $type,
        int     $amountCents,
        string  $currency,
        ?string $tenantId,
        ?string $stripeSessionId,
        array   $metadata = [],
    ): self {
        return new self(
            id:                   \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:             $tenantId,
            stripePaymentIntentId: null,
            stripeInvoiceId:      null,
            stripeSessionId:      $stripeSessionId,
            type:                 $type,
            amountCents:          $amountCents,
            currency:             $currency,
            status:               self::STATUS_PENDING,
            metadata:             $metadata,
            paidAt:               null,
            createdAt:            new \DateTimeImmutable(),
        );
    }

    public static function fromArray(array $data): self
    {
        $metadata = is_string($data['metadata'])
            ? json_decode($data['metadata'], true)
            : $data['metadata'];

        return new self(
            id:                    $data['id'],
            tenantId:              $data['tenant_id'] ?? null,
            stripePaymentIntentId: $data['stripe_payment_intent_id'] ?? null,
            stripeInvoiceId:       $data['stripe_invoice_id'] ?? null,
            stripeSessionId:       $data['stripe_session_id'] ?? null,
            type:                  $data['type'],
            amountCents:           (int) $data['amount_cents'],
            currency:              $data['currency'],
            status:                $data['status'],
            metadata:              $metadata ?? [],
            paidAt:                isset($data['paid_at']) && $data['paid_at']
                                       ? new \DateTimeImmutable($data['paid_at'])
                                       : null,
            createdAt:             new \DateTimeImmutable($data['created_at']),
        );
    }

    public function getId(): string                       { return $this->id; }
    public function getTenantId(): ?string                { return $this->tenantId; }
    public function getStripePaymentIntentId(): ?string   { return $this->stripePaymentIntentId; }
    public function getStripeInvoiceId(): ?string         { return $this->stripeInvoiceId; }
    public function getStripeSessionId(): ?string         { return $this->stripeSessionId; }
    public function getType(): string                     { return $this->type; }
    public function getAmountCents(): int                 { return $this->amountCents; }
    public function getCurrency(): string                 { return $this->currency; }
    public function getStatus(): string                   { return $this->status; }
    public function getMetadata(): array                  { return $this->metadata; }
    public function getPaidAt(): ?\DateTimeImmutable      { return $this->paidAt; }
    public function getCreatedAt(): \DateTimeImmutable    { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'                       => $this->id,
            'tenant_id'                => $this->tenantId,
            'stripe_payment_intent_id' => $this->stripePaymentIntentId,
            'stripe_invoice_id'        => $this->stripeInvoiceId,
            'stripe_session_id'        => $this->stripeSessionId,
            'type'                     => $this->type,
            'amount_cents'             => $this->amountCents,
            'currency'                 => $this->currency,
            'status'                   => $this->status,
            'metadata'                 => $this->metadata,
            'paid_at'                  => $this->paidAt?->format(\DateTimeInterface::ATOM),
            'created_at'               => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
