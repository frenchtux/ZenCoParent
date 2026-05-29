<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

final readonly class CreateExpenseCommand
{
    public function __construct(
        public string  $tenantId,
        public string  $paidBy,
        public float   $amount,
        public string  $description,
        public string  $date,
        public ?string $category,
        public array   $splitRatio = [],
    ) {}
}
