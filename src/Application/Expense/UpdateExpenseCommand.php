<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

final readonly class UpdateExpenseCommand
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public float   $amount,
        public string  $description,
        public string  $date,
        public ?string $category,
        public array   $splitRatio = [],
    ) {}
}
