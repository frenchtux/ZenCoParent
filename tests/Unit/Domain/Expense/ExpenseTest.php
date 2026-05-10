<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\Expense;

use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\Expense\Expense;

final class ExpenseTest extends TestCase
{
    private string $tenantId = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $paidBy   = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    public function test_create_builds_expense_with_required_fields(): void
    {
        $expense = Expense::create(
            tenantId:    $this->tenantId,
            paidBy:      $this->paidBy,
            amount:      150.50,
            description: 'School supplies',
            category:    'education',
            splitRatio:  [$this->paidBy => 50, 'other-user' => 50],
            date:        '2026-06-01',
        );

        $this->assertNotEmpty($expense->getId());
        $this->assertSame($this->tenantId, $expense->getTenantId());
        $this->assertSame($this->paidBy, $expense->getPaidBy());
        $this->assertSame(150.50, $expense->getAmount());
        $this->assertSame('School supplies', $expense->getDescription());
        $this->assertSame('education', $expense->getCategory());
        $this->assertSame('2026-06-01', $expense->getDate()->format('Y-m-d'));
        $this->assertCount(2, $expense->getSplitRatio());
    }

    public function test_with_updated_returns_new_instance(): void
    {
        $original = Expense::create(
            tenantId:    $this->tenantId,
            paidBy:      $this->paidBy,
            amount:      100.0,
            description: 'Original',
            category:    null,
            splitRatio:  [],
            date:        '2026-06-01',
        );

        $updated = $original->withUpdated(
            amount:      200.0,
            description: 'Updated',
            category:    'food',
            splitRatio:  [$this->paidBy => 100],
            date:        '2026-06-15',
        );

        $this->assertNotSame($original, $updated);
        $this->assertSame(100.0,      $original->getAmount());
        $this->assertSame(200.0,      $updated->getAmount());
        $this->assertSame('Updated',  $updated->getDescription());
        $this->assertSame('food',     $updated->getCategory());
        $this->assertSame('2026-06-15', $updated->getDate()->format('Y-m-d'));
        $this->assertGreaterThanOrEqual($original->getUpdatedAt(), $updated->getUpdatedAt());
    }

    public function test_from_array_with_json_split_ratio(): void
    {
        $now  = date('Y-m-d H:i:s');
        $data = [
            'id'          => 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'tenant_id'   => $this->tenantId,
            'paid_by'     => $this->paidBy,
            'amount'      => '75.25',
            'description' => 'Dentist',
            'category'    => 'health',
            'split_ratio' => '{"user1": 60, "user2": 40}',
            'date'        => '2026-05-10',
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        $expense = Expense::fromArray($data);

        $this->assertSame(75.25, $expense->getAmount());
        $this->assertSame(['user1' => 60, 'user2' => 40], $expense->getSplitRatio());
    }

    public function test_to_array_formats_amount_and_date(): void
    {
        $expense = Expense::create(
            tenantId:    $this->tenantId,
            paidBy:      $this->paidBy,
            amount:      99.99,
            description: 'Test',
            category:    null,
            splitRatio:  [],
            date:        '2026-07-04',
        );

        $arr = $expense->toArray();

        $this->assertSame(99.99, $arr['amount']);
        $this->assertSame('2026-07-04', $arr['date']);
        $this->assertIsArray($arr['split_ratio']);
    }
}
