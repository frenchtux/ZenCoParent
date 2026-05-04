<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\MedicalRecord;

final class MedicalRecord
{
    public function __construct(
        private readonly string             $id,
        private readonly string             $tenantId,
        private readonly string             $childId,
        private readonly ?string            $eventId,
        private readonly string             $report,
        private readonly ?string            $practitioner,
        private readonly \DateTimeImmutable $recordedAt,
        private readonly ?string            $createdBy,
        private readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string              $tenantId,
        string              $childId,
        string              $report,
        string              $createdBy,
        ?string             $eventId = null,
        ?string             $practitioner = null,
        ?\DateTimeImmutable $recordedAt = null,
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            id:           \Ramsey\Uuid\Uuid::uuid4()->toString(),
            tenantId:     $tenantId,
            childId:      $childId,
            eventId:      $eventId,
            report:       $report,
            practitioner: $practitioner,
            recordedAt:   $recordedAt ?? $now,
            createdBy:    $createdBy,
            createdAt:    $now,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:           $data['id'],
            tenantId:     $data['tenant_id'],
            childId:      $data['child_id'],
            eventId:      $data['event_id'] ?? null,
            report:       $data['report'],
            practitioner: $data['practitioner'] ?? null,
            recordedAt:   new \DateTimeImmutable($data['recorded_at']),
            createdBy:    $data['created_by'] ?? null,
            createdAt:    new \DateTimeImmutable($data['created_at']),
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

    public function getChildId(): string
    {
        return $this->childId;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function getReport(): string
    {
        return $this->report;
    }

    public function getPractitioner(): ?string
    {
        return $this->practitioner;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'tenant_id'    => $this->tenantId,
            'child_id'     => $this->childId,
            'event_id'     => $this->eventId,
            'report'       => $this->report,
            'practitioner' => $this->practitioner,
            'recorded_at'  => $this->recordedAt->format(\DateTimeInterface::ATOM),
            'created_by'   => $this->createdBy,
            'created_at'   => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
