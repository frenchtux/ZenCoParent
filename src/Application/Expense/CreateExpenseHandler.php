<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Expense;

use ZenCoParent\Domain\Expense\Expense;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class CreateExpenseHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepo,
    ) {}

    public function handle(CreateExpenseCommand $command): ExpenseDTO
    {
        if ($command->amount <= 0) {
            throw ValidationException::withErrors(['amount' => 'Amount must be greater than zero']);
        }

        if (trim($command->description) === '') {
            throw ValidationException::withErrors(['description' => 'Description is required']);
        }

        if (!$this->isValidDate($command->date)) {
            throw ValidationException::withErrors(['date' => 'Invalid date format (expected Y-m-d)']);
        }

        $expense = Expense::create(
            tenantId:    $command->tenantId,
            paidBy:      $command->paidBy,
            amount:      $command->amount,
            description: trim($command->description),
            category:    $command->category !== null ? trim($command->category) : null,
            splitRatio:  $command->splitRatio,
            date:        $command->date,
        );

        $this->expenseRepo->save($expense);

        return ExpenseDTO::fromExpense($expense);
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
