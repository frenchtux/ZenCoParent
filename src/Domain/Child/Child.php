<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Child;

final class Child
{
    public function __construct(
        private readonly string              $id,
        private readonly string              $tenantId,
        private readonly string              $firstName,
        private readonly string              $lastName,
        private readonly ?\DateTimeImmutable $birthdate,
        private readonly array               $medicalInfo,
        private readonly array               $schoolInfo,
        private readonly ?string             $createdBy,
        private readonly \DateTimeImmutable  $createdAt,
        private readonly \DateTimeImmutable  $updatedAt,
    ) {}

    public static function create(
        string  $tenantId,
        string  $firstName,
        string  $lastName,
        ?string $birthdate,
        string  $createdBy,
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            id:          \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:    $tenantId,
            firstName:   $firstName,
            lastName:    $lastName,
            birthdate:   $birthdate !== null ? new \DateTimeImmutable($birthdate) : null,
            medicalInfo: [],
            schoolInfo:  [],
            createdBy:   $createdBy,
            createdAt:   $now,
            updatedAt:   $now,
        );
    }

    public static function fromArray(array $data): self
    {
        $medicalInfo = $data['medical_info'] ?? [];
        if (is_string($medicalInfo)) {
            $medicalInfo = json_decode($medicalInfo, true) ?? [];
        }

        $schoolInfo = $data['school_info'] ?? [];
        if (is_string($schoolInfo)) {
            $schoolInfo = json_decode($schoolInfo, true) ?? [];
        }

        return new self(
            id:          $data['id'],
            tenantId:    $data['tenant_id'],
            firstName:   $data['first_name'],
            lastName:    $data['last_name'],
            birthdate:   isset($data['birthdate']) && $data['birthdate'] !== null
                            ? new \DateTimeImmutable($data['birthdate'])
                            : null,
            medicalInfo: $medicalInfo,
            schoolInfo:  $schoolInfo,
            createdBy:   $data['created_by'] ?? null,
            createdAt:   new \DateTimeImmutable($data['created_at']),
            updatedAt:   new \DateTimeImmutable($data['updated_at']),
        );
    }

    public function withUpdatedInfo(
        string  $firstName,
        string  $lastName,
        ?string $birthdate,
        array   $medicalInfo,
        array   $schoolInfo,
    ): self {
        return new self(
            id:          $this->id,
            tenantId:    $this->tenantId,
            firstName:   $firstName,
            lastName:    $lastName,
            birthdate:   $birthdate !== null ? new \DateTimeImmutable($birthdate) : null,
            medicalInfo: $medicalInfo,
            schoolInfo:  $schoolInfo,
            createdBy:   $this->createdBy,
            createdAt:   $this->createdAt,
            updatedAt:   new \DateTimeImmutable(),
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

    public function getBirthdate(): ?\DateTimeImmutable
    {
        return $this->birthdate;
    }

    public function getMedicalInfo(): array
    {
        return $this->medicalInfo;
    }

    public function getSchoolInfo(): array
    {
        return $this->schoolInfo;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
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
            'id'           => $this->id,
            'tenant_id'    => $this->tenantId,
            'first_name'   => $this->firstName,
            'last_name'    => $this->lastName,
            'birthdate'    => $this->birthdate?->format('Y-m-d'),
            'medical_info' => $this->medicalInfo,
            'school_info'  => $this->schoolInfo,
            'created_by'   => $this->createdBy,
            'created_at'   => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at'   => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public function toPublicArray(): array
    {
        return $this->toArray();
    }
}
