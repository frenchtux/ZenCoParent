<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Tenant;

final class Tenant
{
    public function __construct(
        private readonly string             $id,
        private readonly string             $name,
        private readonly string             $slug,
        private readonly bool               $isActive,
        private readonly ?array             $modulesOverride,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(string $name, string $slug): self
    {
        $now = new \DateTimeImmutable();
        return new self(
            id:              \Ramsey\Uuid\Uuid::uuid4()->toString(),
            name:            $name,
            slug:            $slug,
            isActive:        true,
            modulesOverride: null,
            createdAt:       $now,
            updatedAt:       $now,
        );
    }

    public static function fromArray(array $data): self
    {
        $override = isset($data['modules_override']) && $data['modules_override'] !== null
            ? (is_string($data['modules_override'])
                ? json_decode($data['modules_override'], true)
                : $data['modules_override'])
            : null;

        return new self(
            id:              $data['id'],
            name:            $data['name'],
            slug:            $data['slug'],
            isActive:        (bool) $data['is_active'],
            modulesOverride: $override,
            createdAt:       new \DateTimeImmutable($data['created_at']),
            updatedAt:       new \DateTimeImmutable($data['updated_at']),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getModulesOverride(): ?array
    {
        return $this->modulesOverride;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'is_active'        => $this->isActive,
            'modules_override' => $this->modulesOverride,
            'created_at'       => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at'       => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
