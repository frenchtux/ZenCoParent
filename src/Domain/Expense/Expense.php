<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Expense;

final class Expense
{
    public function __construct(
        private readonly string              $id,
        private readonly string              $tenantId,
        private readonly string              $paidBy,
        private readonly float               $amount,
        private readonly string              $description,
        private readonly ?string             $category,
        private readonly array               $splitRatio,
        private readonly \DateTimeImmutable  $date,
        private readonly \DateTimeImmutable  $createdAt,
        private readonly \DateTimeImmutable  $updatedAt,
    ) {}

    public static function create(
        string $tenantId,
        string $paidBy,
        float  $amount,
        string $description,
        string $date,
        ?string $category = null,
        array   $splitRatio = [],
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            id:          \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:    $tenantId,
            paidBy:      $paidBy,
            amount:      $amount,
            description: $description,
            category:    $category,
            splitRatio:  $splitRatio,
            date:        new \DateTimeImmutable($date),
            createdAt:   $now,
            updatedAt:   $now,
        );
    }

    public static function fromArray(array $data): self
    {
        $splitRaw = $data['split_ratio'] ?? '{}';
        $splitRatio = is_array($splitRaw)
            ? $splitRaw
            : (json_decode((string) $splitRaw, true) ?? []);

        return new self(
            id:          $data['id'],
            tenantId:    $data['tenant_id'],
            paidBy:      $data['paid_by'],
            amount:      (float) $data['amount'],
            description: $data['description'],
            category:    $data['category'] ?? null,
            splitRatio:  $splitRatio,
            date:        new \DateTimeImmutable($data['date']),
            createdAt:   new \DateTimeImmutable($data['created_at']),
            updatedAt:   new \DateTimeImmutable($data['updated_at']),
        );
    }

    public function withUpdated(
        float   $amount,
        string  $description,
        string  $date,
        ?string $category,
    ): self {
        return new self(
            id:          $this->id,
            tenantId:    $this->tenantId,
            paidBy:      $this->paidBy,
            amount:      $amount,
            description: $description,
            category:    $category,
            splitRatio:  $this->splitRatio,
            date:        new \DateTimeImmutable($date),
            createdAt:   $this->createdAt,
            updatedAt:   new \DateTimeImmutable(),
        );
    }

    public function getId(): string        { return $this->id; }
    public function getTenantId(): string  { return $this->tenantId; }
    public function getPaidBy(): string    { return $this->paidBy; }
    public function getAmount(): float     { return $this->amount; }
    public function getDescription(): string { return $this->description; }
    public function getCategory(): ?string { return $this->category; }
    public function getSplitRatio(): array { return $this->splitRatio; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenantId,
            'paid_by'     => $this->paidBy,
            'amount'      => $this->amount,
            'description' => $this->description,
            'category'    => $this->category,
            'split_ratio' => $this->splitRatio,
            'date'        => $this->date->format('Y-m-d'),
            'created_at'  => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at'  => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
