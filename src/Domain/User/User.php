<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\User;

final class User
{
    public function __construct(
        private readonly string              $id,
        private readonly string              $tenantId,
        private readonly string              $email,
        private readonly ?string             $passwordHash,
        private readonly string              $firstName,
        private readonly string              $lastName,
        private readonly ?string             $phone,
        private readonly ?string             $address,
        private readonly UserRole            $role,
        private readonly bool                $isActive,
        private readonly ?\DateTimeImmutable $emailVerifiedAt,
        private readonly ?\DateTimeImmutable $lastLoginAt,
        private readonly \DateTimeImmutable  $createdAt,
        private readonly \DateTimeImmutable  $updatedAt,
    ) {}

    public static function create(
        string   $tenantId,
        string   $email,
        string   $passwordHash,
        string   $firstName,
        string   $lastName,
        UserRole $role = UserRole::Parent,
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            id:              \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:        $tenantId,
            email:           strtolower(trim($email)),
            passwordHash:    $passwordHash,
            firstName:       $firstName,
            lastName:        $lastName,
            phone:           null,
            address:         null,
            role:            $role,
            isActive:        true,
            emailVerifiedAt: null,
            lastLoginAt:     null,
            createdAt:       $now,
            updatedAt:       $now,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:              $data['id'],
            tenantId:        $data['tenant_id'],
            email:           $data['email'],
            passwordHash:    $data['password_hash'] ?? null,
            firstName:       $data['first_name'],
            lastName:        $data['last_name'],
            phone:           $data['phone'] ?? null,
            address:         $data['address'] ?? null,
            role:            UserRole::from($data['role']),
            isActive:        (bool) $data['is_active'],
            emailVerifiedAt: isset($data['email_verified_at']) && $data['email_verified_at'] !== null
                                ? new \DateTimeImmutable($data['email_verified_at'])
                                : null,
            lastLoginAt:     isset($data['last_login_at']) && $data['last_login_at'] !== null
                                ? new \DateTimeImmutable($data['last_login_at'])
                                : null,
            createdAt:       new \DateTimeImmutable($data['created_at']),
            updatedAt:       new \DateTimeImmutable($data['updated_at']),
        );
    }

    public function withUpdatedProfile(
        string  $firstName,
        string  $lastName,
        ?string $phone,
        ?string $address,
    ): self {
        return new self(
            id:              $this->id,
            tenantId:        $this->tenantId,
            email:           $this->email,
            passwordHash:    $this->passwordHash,
            firstName:       $firstName,
            lastName:        $lastName,
            phone:           $phone,
            address:         $address,
            role:            $this->role,
            isActive:        $this->isActive,
            emailVerifiedAt: $this->emailVerifiedAt,
            lastLoginAt:     $this->lastLoginAt,
            createdAt:       $this->createdAt,
            updatedAt:       new \DateTimeImmutable(),
        );
    }

    public function withLastLogin(): self
    {
        return new self(
            id:              $this->id,
            tenantId:        $this->tenantId,
            email:           $this->email,
            passwordHash:    $this->passwordHash,
            firstName:       $this->firstName,
            lastName:        $this->lastName,
            phone:           $this->phone,
            address:         $this->address,
            role:            $this->role,
            isActive:        $this->isActive,
            emailVerifiedAt: $this->emailVerifiedAt,
            lastLoginAt:     new \DateTimeImmutable(),
            createdAt:       $this->createdAt,
            updatedAt:       new \DateTimeImmutable(),
        );
    }

    public function withNewPassword(string $passwordHash): self
    {
        return new self(
            id:              $this->id,
            tenantId:        $this->tenantId,
            email:           $this->email,
            passwordHash:    $passwordHash,
            firstName:       $this->firstName,
            lastName:        $this->lastName,
            phone:           $this->phone,
            address:         $this->address,
            role:            $this->role,
            isActive:        $this->isActive,
            emailVerifiedAt: $this->emailVerifiedAt,
            lastLoginAt:     $this->lastLoginAt,
            createdAt:       $this->createdAt,
            updatedAt:       new \DateTimeImmutable(),
        );
    }

    public function withAdminFields(UserRole $role, bool $isActive): self
    {
        return new self(
            id:              $this->id,
            tenantId:        $this->tenantId,
            email:           $this->email,
            passwordHash:    $this->passwordHash,
            firstName:       $this->firstName,
            lastName:        $this->lastName,
            phone:           $this->phone,
            address:         $this->address,
            role:            $role,
            isActive:        $isActive,
            emailVerifiedAt: $this->emailVerifiedAt,
            lastLoginAt:     $this->lastLoginAt,
            createdAt:       $this->createdAt,
            updatedAt:       new \DateTimeImmutable(),
        );
    }

    public function withEmailVerified(): self
    {
        return new self(
            id:              $this->id,
            tenantId:        $this->tenantId,
            email:           $this->email,
            passwordHash:    $this->passwordHash,
            firstName:       $this->firstName,
            lastName:        $this->lastName,
            phone:           $this->phone,
            address:         $this->address,
            role:            $this->role,
            isActive:        $this->isActive,
            emailVerifiedAt: new \DateTimeImmutable(),
            lastLoginAt:     $this->lastLoginAt,
            createdAt:       $this->createdAt,
            updatedAt:       new \DateTimeImmutable(),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
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
            'id'                => $this->id,
            'tenant_id'         => $this->tenantId,
            'email'             => $this->email,
            'password_hash'     => $this->passwordHash,
            'first_name'        => $this->firstName,
            'last_name'         => $this->lastName,
            'phone'             => $this->phone,
            'address'           => $this->address,
            'role'              => $this->role->value,
            'is_active'         => $this->isActive,
            'email_verified_at' => $this->emailVerifiedAt?->format(\DateTimeInterface::ATOM),
            'last_login_at'     => $this->lastLoginAt?->format(\DateTimeInterface::ATOM),
            'created_at'        => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at'        => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public function toPublicArray(): array
    {
        $data = $this->toArray();
        unset($data['password_hash']);
        return $data;
    }
}
