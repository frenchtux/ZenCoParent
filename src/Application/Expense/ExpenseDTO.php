<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

final readonly class ExpenseDTO
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public string  $paidBy,
        public float   $amount,
        public string  $description,
        public ?string $category,
        public array   $splitRatio,
        public string  $date,
        public string  $createdAt,
        public string  $updatedAt,
    ) {}

    public static function fromExpense(\ZenCoParent\Domain\Expense\Expense $expense): self
    {
        return new self(
            id:          $expense->getId(),
            tenantId:    $expense->getTenantId(),
            paidBy:      $expense->getPaidBy(),
            amount:      $expense->getAmount(),
            description: $expense->getDescription(),
            category:    $expense->getCategory(),
            splitRatio:  $expense->getSplitRatio(),
            date:        $expense->getDate()->format('Y-m-d'),
            createdAt:   $expense->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt:   $expense->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

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
            'date'        => $this->date,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
