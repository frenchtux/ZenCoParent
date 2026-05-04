<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence;

final class PDOTransactionManager implements \ZenCoParent\Domain\Shared\TransactionManagerInterface
{
    public function __construct(private \PDO $pdo) {}

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
