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
        $errors = [];

        if ($command->amount <= 0) {
            $errors['amount'] = 'Amount must be greater than zero.';
        }

        if (trim($command->description) === '') {
            $errors['description'] = 'Description cannot be blank.';
        }

        if (!\DateTimeImmutable::createFromFormat('Y-m-d', $command->date)) {
            $errors['date'] = 'Invalid date. Expected Y-m-d format.';
        }

        if (!empty($errors)) {
            throw ValidationException::withErrors($errors);
        }

        $expense = Expense::create(
            tenantId:    $command->tenantId,
            paidBy:      $command->paidBy,
            amount:      $command->amount,
            description: $command->description,
            date:        $command->date,
            category:    $command->category,
        );

        $this->expenseRepo->save($expense);

        return ExpenseDTO::fromExpense($expense);
    }
}
