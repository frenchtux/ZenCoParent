<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\SQLite;

use ZenCoParent\Domain\Expense\Expense;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class SQLiteExpenseRepository extends AbstractRepository implements ExpenseRepositoryInterface
{
    public function findById(string $id): ?Expense
    {
        $stmt = $this->pdo->prepare('SELECT * FROM expenses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Expense::fromArray($row) : null;
    }

    public function findByTenantId(string $tenantId, ?string $from = null, ?string $to = null): array
    {
        $params = ['tenant_id' => $tenantId];
        $sql    = 'SELECT * FROM expenses WHERE tenant_id = :tenant_id';

        if ($from !== null) {
            $sql .= ' AND date >= :from';
            $params['from'] = $from;
        }

        if ($to !== null) {
            $sql .= ' AND date <= :to';
            $params['to'] = $to;
        }

        $sql .= ' ORDER BY date DESC, created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static fn(array $row): Expense => Expense::fromArray($row), $rows);
    }

    public function save(Expense $expense): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO expenses (
                id, tenant_id, paid_by, amount, description, category,
                split_ratio, date, created_at, updated_at
            ) VALUES (
                :id, :tenant_id, :paid_by, :amount, :description, :category,
                :split_ratio, :date, datetime(\'now\'), datetime(\'now\')
            )'
        );
        $stmt->execute([
            'id'          => $expense->getId(),
            'tenant_id'   => $expense->getTenantId(),
            'paid_by'     => $expense->getPaidBy(),
            'amount'      => $expense->getAmount(),
            'description' => $expense->getDescription(),
            'category'    => $expense->getCategory(),
            'split_ratio' => json_encode($expense->getSplitRatio()),
            'date'        => $expense->getDate()->format('Y-m-d'),
        ]);
    }

    public function update(Expense $expense): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE expenses SET
                amount      = :amount,
                description = :description,
                category    = :category,
                date        = :date,
                updated_at  = datetime(\'now\')
            WHERE id = :id'
        );
        $stmt->execute([
            'id'          => $expense->getId(),
            'amount'      => $expense->getAmount(),
            'description' => $expense->getDescription(),
            'category'    => $expense->getCategory(),
            'date'        => $expense->getDate()->format('Y-m-d'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
