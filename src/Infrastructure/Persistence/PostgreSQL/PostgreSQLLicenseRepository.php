<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Persistence\PostgreSQL;

use ZenCoParent\Domain\License\License;
use ZenCoParent\Domain\License\LicenseRepositoryInterface;
use ZenCoParent\Infrastructure\Persistence\AbstractRepository;

final class PostgreSQLLicenseRepository extends AbstractRepository implements LicenseRepositoryInterface
{
    public function get(): ?License
    {
        $stmt = $this->pdo->query('SELECT * FROM app_license LIMIT 1');
        $row  = $stmt->fetch();
        return $row !== false ? License::fromArray($row) : null;
    }

    public function save(License $license): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_license
                (id, installation_key, activation_key, installed_at, activated_at, is_active, instance_id)
             VALUES
                (:id, :installation_key, :activation_key, :installed_at, :activated_at, :is_active, :instance_id)'
        );
        $stmt->execute([
            'id'               => $license->getId(),
            'installation_key' => $license->getInstallationKey(),
            'activation_key'   => $license->getActivationKey(),
            'installed_at'     => $license->getInstalledAt()->format('Y-m-d H:i:s'),
            'activated_at'     => $license->getActivatedAt()?->format('Y-m-d H:i:s'),
            'is_active'        => $license->isActive() ? 'true' : 'false',
            'instance_id'      => $license->getInstanceId(),
        ]);
    }

    public function update(License $license): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE app_license SET
                activation_key = :activation_key,
                activated_at   = :activated_at,
                is_active      = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            'id'             => $license->getId(),
            'activation_key' => $license->getActivationKey(),
            'activated_at'   => $license->getActivatedAt()?->format('Y-m-d H:i:s'),
            'is_active'      => $license->isActive() ? 'true' : 'false',
        ]);
    }
}
