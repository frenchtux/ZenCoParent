<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Expense\Expense;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLExpenseRepository extends AbstractRepository implements ExpenseRepositoryInterface
{
    public function findById(string $id): ?Expense
    {
        $stmt = $this->pdo->prepare('SELECT * FROM expenses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Expense::fromArray($row) : null;
    }

    public function findByTenantId(
        string  $tenantId,
        ?string $paidBy   = null,
        ?string $category = null,
        ?string $from     = null,
        ?string $to       = null,
    ): array {
        $params = ['tenant_id' => $tenantId];
        $sql    = 'SELECT * FROM expenses WHERE tenant_id = :tenant_id';

        if ($paidBy !== null) {
            $sql .= ' AND paid_by = :paid_by';
            $params['paid_by'] = $paidBy;
        }
        if ($category !== null) {
            $sql .= ' AND category = :category';
            $params['category'] = $category;
        }
        if ($from !== null) {
            $sql .= ' AND date >= :from';
            $params['from'] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND date <= :to';
            $params['to'] = $to;
        }

        $sql .= ' ORDER BY date DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn(array $row): Expense => Expense::fromArray($row), $stmt->fetchAll());
    }

    public function save(Expense $expense): void
    {
        $now  = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO expenses (id, tenant_id, paid_by, amount, description, category, split_ratio, date, created_at, updated_at)
             VALUES (:id, :tenant_id, :paid_by, :amount, :description, :category, :split_ratio, :date, :created_at, :updated_at)'
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
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function update(Expense $expense): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE expenses SET
                amount      = :amount,
                description = :description,
                category    = :category,
                split_ratio = :split_ratio,
                date        = :date,
                updated_at  = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id'          => $expense->getId(),
            'amount'      => $expense->getAmount(),
            'description' => $expense->getDescription(),
            'category'    => $expense->getCategory(),
            'split_ratio' => json_encode($expense->getSplitRatio()),
            'date'        => $expense->getDate()->format('Y-m-d'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM expenses WHERE id = :id')->execute(['id' => $id]);
    }

    public function existsForTenant(string $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM expenses WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
