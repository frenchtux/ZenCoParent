<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Plan;

final class Plan
{
    public function __construct(
        private readonly string             $id,
        private readonly string             $name,
        private readonly string             $displayName,
        private readonly string             $description,
        private readonly int                $priceMonthyCents,
        private readonly int                $priceYearlyCents,
        private readonly ?string            $stripePriceIdMonthly,
        private readonly ?string            $stripePriceIdYearly,
        private readonly array              $modules,
        private readonly bool               $isActive,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        $modules = is_string($data['modules'])
            ? json_decode($data['modules'], true)
            : $data['modules'];

        return new self(
            id:                   $data['id'],
            name:                 $data['name'],
            displayName:          $data['display_name'],
            description:          $data['description'] ?? '',
            priceMonthyCents:     (int) $data['price_monthly_cents'],
            priceYearlyCents:     (int) $data['price_yearly_cents'],
            stripePriceIdMonthly: $data['stripe_price_id_monthly'] ?? null,
            stripePriceIdYearly:  $data['stripe_price_id_yearly'] ?? null,
            modules:              $modules ?? [],
            isActive:             (bool) $data['is_active'],
            createdAt:            new \DateTimeImmutable($data['created_at']),
            updatedAt:            new \DateTimeImmutable($data['updated_at']),
        );
    }

    public function isModuleIncluded(string $module): bool
    {
        return (bool) ($this->modules[$module] ?? false);
    }

    public function getId(): string              { return $this->id; }
    public function getName(): string            { return $this->name; }
    public function getDisplayName(): string     { return $this->displayName; }
    public function getDescription(): string     { return $this->description; }
    public function getPriceMonthyCents(): int   { return $this->priceMonthyCents; }
    public function getPriceYearlyCents(): int   { return $this->priceYearlyCents; }
    public function getStripePriceIdMonthly(): ?string { return $this->stripePriceIdMonthly; }
    public function getStripePriceIdYearly(): ?string  { return $this->stripePriceIdYearly; }
    public function getModules(): array          { return $this->modules; }
    public function isActive(): bool             { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'display_name'            => $this->displayName,
            'description'             => $this->description,
            'price_monthly_cents'     => $this->priceMonthyCents,
            'price_yearly_cents'      => $this->priceYearlyCents,
            'stripe_price_id_monthly' => $this->stripePriceIdMonthly,
            'stripe_price_id_yearly'  => $this->stripePriceIdYearly,
            'modules'                 => $this->modules,
            'is_active'               => $this->isActive,
            'created_at'              => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at'              => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
