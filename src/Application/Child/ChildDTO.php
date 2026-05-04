<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Child;

final readonly class ChildDTO
{
    public function __construct(
        public string  $id,
        public string  $tenantId,
        public string  $firstName,
        public string  $lastName,
        public ?string $birthdate,
        public array   $medicalInfo,
        public array   $schoolInfo,
        public ?string $createdBy,
        public string  $createdAt,
    ) {}

    public static function fromChild(\ZenCoParent\Domain\Child\Child $child): self
    {
        return new self(
            id:          $child->getId(),
            tenantId:    $child->getTenantId(),
            firstName:   $child->getFirstName(),
            lastName:    $child->getLastName(),
            birthdate:   $child->getBirthdate()?->format('Y-m-d'),
            medicalInfo: $child->getMedicalInfo(),
            schoolInfo:  $child->getSchoolInfo(),
            createdBy:   $child->getCreatedBy(),
            createdAt:   $child->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'tenant_id'    => $this->tenantId,
            'first_name'   => $this->firstName,
            'last_name'    => $this->lastName,
            'birthdate'    => $this->birthdate,
            'medical_info' => $this->medicalInfo,
            'school_info'  => $this->schoolInfo,
            'created_by'   => $this->createdBy,
            'created_at'   => $this->createdAt,
        ];
    }
}
