<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence;

abstract class AbstractRepository
{
    protected \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}
