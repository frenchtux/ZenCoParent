<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Child;

use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class UpdateChildHandler
{
    public function __construct(
        private ChildRepositoryInterface $childRepo,
    ) {}

    public function handle(UpdateChildCommand $command): ChildDTO
    {
        $child = $this->childRepo->findById($command->id);

        if ($child === null || $child->getTenantId() !== $command->tenantId) {
            throw new NotFoundException('Enfant introuvable.');
        }

        $updated = $child->withUpdatedInfo(
            firstName:   $command->firstName,
            lastName:    $command->lastName,
            birthdate:   $command->birthdate,
            medicalInfo: $child->getMedicalInfo(),
            schoolInfo:  $child->getSchoolInfo(),
        );

        $this->childRepo->update($updated);

        return ChildDTO::fromChild($updated);
    }
}
