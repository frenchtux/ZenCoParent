<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Subscription;

final class Subscription
{
    public const STATUS_TRIAL     = 'trial';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAST_DUE  = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    public function __construct(
        private readonly string              $id,
        private readonly string              $tenantId,
        private readonly ?string             $planId,
        private readonly ?string             $stripeCustomerId,
        private readonly ?string             $stripeSubscriptionId,
        private readonly string              $status,
        private readonly ?string             $billingInterval,
        private readonly ?\DateTimeImmutable $currentPeriodStart,
        private readonly ?\DateTimeImmutable $currentPeriodEnd,
        private readonly ?\DateTimeImmutable $trialEndsAt,
        private readonly ?\DateTimeImmutable $cancelledAt,
        private readonly \DateTimeImmutable  $createdAt,
        private readonly \DateTimeImmutable  $updatedAt,
    ) {}

    public static function createTrial(string $tenantId): self
    {
        $now = new \DateTimeImmutable();
        return new self(
            id:                   \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:             $tenantId,
            planId:               null,
            stripeCustomerId:     null,
            stripeSubscriptionId: null,
            status:               self::STATUS_TRIAL,
            billingInterval:      null,
            currentPeriodStart:   null,
            currentPeriodEnd:     null,
            trialEndsAt:          $now->modify('+30 days'),
            cancelledAt:          null,
            createdAt:            $now,
            updatedAt:            $now,
        );
    }

    public static function fromArray(array $data): self
    {
        $dt = static fn(?string $v) => $v ? new \DateTimeImmutable($v) : null;
        return new self(
            id:                   $data['id'],
            tenantId:             $data['tenant_id'],
            planId:               $data['plan_id'] ?? null,
            stripeCustomerId:     $data['stripe_customer_id'] ?? null,
            stripeSubscriptionId: $data['stripe_subscription_id'] ?? null,
            status:               $data['status'],
            billingInterval:      $data['billing_interval'] ?? null,
            currentPeriodStart:   $dt($data['current_period_start'] ?? null),
            currentPeriodEnd:     $dt($data['current_period_end'] ?? null),
            trialEndsAt:          $dt($data['trial_ends_at'] ?? null),
            cancelledAt:          $dt($data['cancelled_at'] ?? null),
            createdAt:            new \DateTimeImmutable($data['created_at']),
            updatedAt:            new \DateTimeImmutable($data['updated_at']),
        );
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE], true);
    }

    public function isTrialExpired(): bool
    {
        if ($this->status !== self::STATUS_TRIAL || $this->trialEndsAt === null) {
            return false;
        }
        return new \DateTimeImmutable() > $this->trialEndsAt;
    }

    public function getId(): string                          { return $this->id; }
    public function getTenantId(): string                    { return $this->tenantId; }
    public function getPlanId(): ?string                     { return $this->planId; }
    public function getStripeCustomerId(): ?string           { return $this->stripeCustomerId; }
    public function getStripeSubscriptionId(): ?string       { return $this->stripeSubscriptionId; }
    public function getStatus(): string                      { return $this->status; }
    public function getBillingInterval(): ?string            { return $this->billingInterval; }
    public function getCurrentPeriodEnd(): ?\DateTimeImmutable { return $this->currentPeriodEnd; }
    public function getTrialEndsAt(): ?\DateTimeImmutable    { return $this->trialEndsAt; }
    public function getCancelledAt(): ?\DateTimeImmutable    { return $this->cancelledAt; }
    public function getCreatedAt(): \DateTimeImmutable       { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'tenant_id'             => $this->tenantId,
            'plan_id'               => $this->planId,
            'stripe_customer_id'    => $this->stripeCustomerId,
            'stripe_subscription_id'=> $this->stripeSubscriptionId,
            'status'                => $this->status,
            'billing_interval'      => $this->billingInterval,
            'current_period_start'  => $this->currentPeriodStart?->format(\DateTimeInterface::ATOM),
            'current_period_end'    => $this->currentPeriodEnd?->format(\DateTimeInterface::ATOM),
            'trial_ends_at'         => $this->trialEndsAt?->format(\DateTimeInterface::ATOM),
            'cancelled_at'          => $this->cancelledAt?->format(\DateTimeInterface::ATOM),
            'created_at'            => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at'            => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
