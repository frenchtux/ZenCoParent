<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

use ZenCoParent\Domain\Messaging\Thread;
use ZenCoParent\Domain\Messaging\ThreadType;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class CreateThreadHandler
{
    public function __construct(
        private ThreadRepositoryInterface $threadRepo,
        private UserRepositoryInterface   $userRepo,
    ) {}

    public function handle(CreateThreadCommand $command): ThreadDTO
    {
        // 1. Validate type is a valid ThreadType value
        $validValues = array_map(fn(ThreadType $t) => $t->value, ThreadType::cases());
        if (!in_array($command->type, $validValues, true)) {
            throw ValidationException::withErrors([
                'type' => sprintf(
                    'Invalid thread type "%s". Allowed values: %s.',
                    $command->type,
                    implode(', ', $validValues),
                ),
            ]);
        }

        // 2. Parse ThreadType
        $type = ThreadType::from($command->type);

        // 3. Build participant list
        $participantIds = $command->participantIds;

        // Ensure creator is always included
        if (!in_array($command->createdBy, $participantIds, true)) {
            $participantIds[] = $command->createdBy;
        }

        // Deduplicate
        $participantIds = array_values(array_unique($participantIds));

        // If only the creator remains (i.e. no additional participants were provided),
        // auto-populate from tenant users
        if (count($participantIds) === 1 && $participantIds[0] === $command->createdBy) {
            $tenantUsers = $this->userRepo->findByTenantId($command->tenantId);

            if ($type === ThreadType::Parents) {
                $tenantUsers = array_filter(
                    $tenantUsers,
                    fn($user) => $user->getRole() === UserRole::Parent,
                );
            }

            $participantIds = array_values(array_unique(array_map(
                fn($user) => $user->getId(),
                $tenantUsers,
            )));

            // Always keep creator even if not returned by the repo
            if (!in_array($command->createdBy, $participantIds, true)) {
                $participantIds[] = $command->createdBy;
            }
        }

        // 4. Validate participants based on type
        if ($type === ThreadType::Parents) {
            foreach ($participantIds as $participantId) {
                $user = $this->userRepo->findById($participantId);
                if ($user === null || $user->getRole() !== UserRole::Parent) {
                    throw ValidationException::withErrors([
                        'participantIds' => sprintf(
                            'User "%s" is not a parent and cannot participate in a parents thread.',
                            $participantId,
                        ),
                    ]);
                }
            }
        }
        // family type: no role restriction

        // 5. Create Thread aggregate
        $thread = Thread::create($command->tenantId, $type, $participantIds);

        // 6. Persist (repository is responsible for also persisting thread_participants)
        $this->threadRepo->save($thread);

        // 7. Return DTO with 0 unread messages for a brand-new thread
        return ThreadDTO::fromThread($thread, 0);
    }
}
