<?php
declare(strict_types=1);

namespace ZenCoParent\Application\MedicalRecord;

use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class GetChildMedicalHistoryHandler
{
    public function __construct(
        private MedicalRecordRepositoryInterface $medicalRepo,
        private ChildRepositoryInterface         $childRepo,
    ) {}

    /**
     * @return MedicalRecordDTO[] ordered by recordedAt DESC
     */
    public function handle(string $childId, string $tenantId): array
    {
        // Verify child exists and belongs to tenant
        $child = $this->childRepo->findById($childId)
            ?? throw NotFoundException::forEntity('Child', $childId);

        if ($child->getTenantId() !== $tenantId) {
            throw NotFoundException::forEntity('Child', $childId);
        }

        $records = $this->medicalRepo->findByChildId($childId, $tenantId);

        return array_map(fn($r) => MedicalRecordDTO::fromRecord($r), $records);
    }
}
