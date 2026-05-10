<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\Expense;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\Expense\CreateExpenseCommand;
use ZenCoParent\Application\Expense\CreateExpenseHandler;
use ZenCoParent\Application\Expense\ExpenseDTO;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class CreateExpenseHandlerTest extends TestCase
{
    private MockInterface $expenseRepo;
    private CreateExpenseHandler $handler;

    private string $tenantId = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId   = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->expenseRepo = Mockery::mock(ExpenseRepositoryInterface::class);
        $this->handler     = new CreateExpenseHandler($this->expenseRepo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_creates_expense_with_valid_data(): void
    {
        $this->expenseRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateExpenseCommand(
            tenantId:    $this->tenantId,
            paidBy:      $this->userId,
            amount:      120.0,
            description: 'Groceries',
            category:    'food',
            splitRatio:  [$this->userId => 50],
            date:        '2026-06-01',
        ));

        $this->assertInstanceOf(ExpenseDTO::class, $result);
        $this->assertSame(120.0, $result->amount);
        $this->assertSame('Groceries', $result->description);
        $this->assertSame('food', $result->category);
    }

    public function test_throws_validation_for_zero_amount(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateExpenseCommand(
            tenantId:    $this->tenantId,
            paidBy:      $this->userId,
            amount:      0.0,
            description: 'Nothing',
            category:    null,
            splitRatio:  [],
            date:        '2026-06-01',
        ));
    }

    public function test_throws_validation_for_negative_amount(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateExpenseCommand(
            tenantId:    $this->tenantId,
            paidBy:      $this->userId,
            amount:      -10.0,
            description: 'Refund',
            category:    null,
            splitRatio:  [],
            date:        '2026-06-01',
        ));
    }

    public function test_throws_validation_for_empty_description(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateExpenseCommand(
            tenantId:    $this->tenantId,
            paidBy:      $this->userId,
            amount:      50.0,
            description: '   ',
            category:    null,
            splitRatio:  [],
            date:        '2026-06-01',
        ));
    }

    public function test_throws_validation_for_invalid_date(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateExpenseCommand(
            tenantId:    $this->tenantId,
            paidBy:      $this->userId,
            amount:      50.0,
            description: 'Test',
            category:    null,
            splitRatio:  [],
            date:        'not-a-date',
        ));
    }

    public function test_empty_split_ratio_is_valid(): void
    {
        $this->expenseRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateExpenseCommand(
            tenantId:    $this->tenantId,
            paidBy:      $this->userId,
            amount:      50.0,
            description: 'Coffee',
            category:    null,
            splitRatio:  [],
            date:        '2026-06-01',
        ));

        $this->assertSame([], $result->splitRatio);
    }
}
