<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\Plan\Plan;
use ZenCoParent\Domain\Plan\PlanRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLPlanRepository extends AbstractRepository implements PlanRepositoryInterface
{
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM plans ORDER BY price_monthly_cents ASC');
        return array_map(fn($r) => Plan::fromArray($r), $stmt->fetchAll());
    }

    public function findById(string $id): ?Plan
    {
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? Plan::fromArray($row) : null;
    }

    public function findByName(string $name): ?Plan
    {
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return $row !== false ? Plan::fromArray($row) : null;
    }

    public function save(Plan $plan): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO plans
                (id, name, display_name, description,
                 price_monthly_cents, price_yearly_cents,
                 stripe_price_id_monthly, stripe_price_id_yearly,
                 modules, is_active, created_at, updated_at)
             VALUES
                (:id, :name, :display_name, :description,
                 :price_monthly_cents, :price_yearly_cents,
                 :stripe_price_id_monthly, :stripe_price_id_yearly,
                 :modules, :is_active, NOW(), NOW())'
        );
        $stmt->execute([
            'id'                      => $plan->getId(),
            'name'                    => $plan->getName(),
            'display_name'            => $plan->getDisplayName(),
            'description'             => $plan->getDescription(),
            'price_monthly_cents'     => $plan->getPriceMonthyCents(),
            'price_yearly_cents'      => $plan->getPriceYearlyCents(),
            'stripe_price_id_monthly' => $plan->getStripePriceIdMonthly(),
            'stripe_price_id_yearly'  => $plan->getStripePriceIdYearly(),
            'modules'                 => json_encode($plan->getModules()),
            'is_active'               => $plan->isActive() ? 'true' : 'false',
        ]);
    }

    public function update(string $id, array $fields): void
    {
        $allowed = [
            'display_name', 'description',
            'price_monthly_cents', 'price_yearly_cents',
            'stripe_price_id_monthly', 'stripe_price_id_yearly',
            'modules', 'is_active',
        ];
        $set = [];
        $params = ['id' => $id];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $set[] = "$key = :$key";
            $params[$key] = ($key === 'modules' && is_array($value))
                ? json_encode($value)
                : $value;
        }
        if (empty($set)) {
            return;
        }
        $set[] = 'updated_at = NOW()';
        $this->pdo->prepare('UPDATE plans SET ' . implode(', ', $set) . ' WHERE id = :id')
                  ->execute($params);
    }
}
