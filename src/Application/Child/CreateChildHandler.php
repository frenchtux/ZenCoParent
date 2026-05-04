<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Child;

use ZenCoParent\Domain\Child\Child;
use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class CreateChildHandler
{
    public function __construct(
        private ChildRepositoryInterface $childRepo,
    ) {}

    public function handle(CreateChildCommand $command): ChildDTO
    {
        // Validate first name not empty
        if (trim($command->firstName) === '') {
            throw ValidationException::withErrors(['first_name' => 'First name is required.']);
        }

        // Validate last name not empty
        if (trim($command->lastName) === '') {
            throw ValidationException::withErrors(['last_name' => 'Last name is required.']);
        }

        // Create Child entity and save
        $child = Child::create(
            tenantId:  $command->tenantId,
            firstName: $command->firstName,
            lastName:  $command->lastName,
            birthdate: $command->birthdate,
            createdBy: $command->createdBy,
        );

        $this->childRepo->save($child);

        return ChildDTO::fromChild($child);
    }
}
