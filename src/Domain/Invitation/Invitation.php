<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Invitation;

final class Invitation
{
    public function __construct(
        private readonly string              $id,
        private readonly string              $tenantId,
        private readonly string              $invitedBy,
        private readonly string              $email,
        private readonly string              $role,
        private readonly string              $token,
        private readonly ?\DateTimeImmutable $acceptedAt,
        private readonly \DateTimeImmutable  $expiresAt,
        private readonly \DateTimeImmutable  $createdAt,
    ) {}

    public static function create(
        string $tenantId,
        string $invitedBy,
        string $email,
        string $role,
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            id:         \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:   $tenantId,
            invitedBy:  $invitedBy,
            email:      strtolower(trim($email)),
            role:       $role,
            token:      bin2hex(random_bytes(32)),
            acceptedAt: null,
            expiresAt:  $now->modify('+7 days'),
            createdAt:  $now,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:         $data['id'],
            tenantId:   $data['tenant_id'],
            invitedBy:  $data['invited_by'],
            email:      $data['email'],
            role:       $data['role'],
            token:      $data['token'],
            acceptedAt: isset($data['accepted_at']) && $data['accepted_at'] !== null
                            ? new \DateTimeImmutable($data['accepted_at'])
                            : null,
            expiresAt:  new \DateTimeImmutable($data['expires_at']),
            createdAt:  new \DateTimeImmutable($data['created_at']),
        );
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isAccepted(): bool
    {
        return $this->acceptedAt !== null;
    }

    public function withAccepted(): self
    {
        return new self(
            id:         $this->id,
            tenantId:   $this->tenantId,
            invitedBy:  $this->invitedBy,
            email:      $this->email,
            role:       $this->role,
            token:      $this->token,
            acceptedAt: new \DateTimeImmutable(),
            expiresAt:  $this->expiresAt,
            createdAt:  $this->createdAt,
        );
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getInvitedBy(): string { return $this->invitedBy; }
    public function getEmail(): string { return $this->email; }
    public function getRole(): string { return $this->role; }
    public function getToken(): string { return $this->token; }
    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenantId,
            'invited_by'  => $this->invitedBy,
            'email'       => $this->email,
            'role'        => $this->role,
            'token'       => $this->token,
            'accepted_at' => $this->acceptedAt?->format(\DateTimeInterface::ATOM),
            'expires_at'  => $this->expiresAt->format(\DateTimeInterface::ATOM),
            'created_at'  => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
