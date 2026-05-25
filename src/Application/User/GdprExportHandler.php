<?php
declare(strict_types=1);

namespace ZenCoParent\Application\User;

use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\Expense\ExpenseRepositoryInterface;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Photo\PhotoRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;

final class GdprExportHandler
{
    public function __construct(
        private readonly UserRepositoryInterface          $userRepo,
        private readonly ChildRepositoryInterface         $childRepo,
        private readonly EventRepositoryInterface         $eventRepo,
        private readonly ExpenseRepositoryInterface       $expenseRepo,
        private readonly PhotoRepositoryInterface         $photoRepo,
        private readonly ThreadRepositoryInterface        $threadRepo,
        private readonly MessageRepositoryInterface       $messageRepo,
        private readonly MedicalRecordRepositoryInterface $medicalRepo,
    ) {}

    public function handle(string $userId, string $tenantId): array
    {
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            throw new \ZenCoParent\Domain\Shared\Exception\NotFoundException('User not found');
        }

        $profile = $user->toPublicArray();

        $children = array_map(
            fn($c) => $c->toArray(),
            $this->childRepo->findByTenantId($tenantId),
        );

        $events = array_map(
            fn($e) => $e->toArray(),
            $this->eventRepo->findByTenantId($tenantId),
        );

        $expenses = array_map(
            fn($e) => $e->toArray(),
            $this->expenseRepo->findByTenantId($tenantId),
        );

        $photos = array_map(
            fn($p) => $p->toArray(),
            $this->photoRepo->findByTenantId($tenantId),
        );

        // Threads + messages the user participates in
        $threads = $this->threadRepo->findByUserId($userId, $tenantId);
        $messaging = [];
        foreach ($threads as $thread) {
            $messages = $this->messageRepo->findByThreadId($thread->getId(), null, 1000);
            $messaging[] = [
                'thread'   => $thread->toArray(),
                'messages' => array_map(fn($m) => $m->toArray(), $messages),
            ];
        }

        // Medical records per child
        $medical = [];
        foreach ($children as $child) {
            $records = $this->medicalRepo->findByChildId($child['id'], $tenantId);
            foreach ($records as $r) {
                $medical[] = $r->toArray();
            }
        }

        return [
            'exported_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'profile'     => $profile,
            'children'    => $children,
            'events'      => $events,
            'expenses'    => $expenses,
            'photos'      => $photos,
            'messaging'   => $messaging,
            'medical'     => $medical,
        ];
    }
}
