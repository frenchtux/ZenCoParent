<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

final readonly class UserDTO
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public string  $email,
        public string  $firstName,
        public string  $lastName,
        public ?string $phone,
        public ?string $address,
        public string  $role,
        public bool    $isActive,
        public ?string $emailVerifiedAt,
        public string  $createdAt,
    ) {}

    public static function fromUser(\ZenCoParent\Domain\User\User $user): self
    {
        return new self(
            id:              $user->getId(),
            tenantId:        $user->getTenantId(),
            email:           $user->getEmail(),
            firstName:       $user->getFirstName(),
            lastName:        $user->getLastName(),
            phone:           $user->getPhone(),
            address:         $user->getAddress(),
            role:            $user->getRole()->value,
            isActive:        $user->isActive(),
            emailVerifiedAt: $user->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
            createdAt:       $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'tenant_id'         => $this->tenantId,
            'email'             => $this->email,
            'first_name'        => $this->firstName,
            'last_name'         => $this->lastName,
            'phone'             => $this->phone,
            'address'           => $this->address,
            'role'              => $this->role,
            'is_active'         => $this->isActive,
            'email_verified_at' => $this->emailVerifiedAt,
            'created_at'        => $this->createdAt,
        ];
    }
}
