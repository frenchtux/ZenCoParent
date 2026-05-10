<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\Messaging;

use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\Messaging\Thread;
use ZenCoParent\Domain\Messaging\ThreadType;

final class ThreadTest extends TestCase
{
    private string $tenantId = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId1  = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId2  = 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    public function test_create_builds_thread_with_participants(): void
    {
        $thread = Thread::create($this->tenantId, ThreadType::Parents, [$this->userId1, $this->userId2]);

        $this->assertNotEmpty($thread->getId());
        $this->assertSame($this->tenantId, $thread->getTenantId());
        $this->assertSame(ThreadType::Parents, $thread->getType());
        $this->assertCount(2, $thread->getParticipantIds());
        $this->assertContains($this->userId1, $thread->getParticipantIds());
    }

    public function test_create_with_empty_participants(): void
    {
        $thread = Thread::create($this->tenantId, ThreadType::Family);
        $this->assertSame([], $thread->getParticipantIds());
    }

    public function test_with_participant_added_returns_new_instance(): void
    {
        $original = Thread::create($this->tenantId, ThreadType::Parents, [$this->userId1]);
        $updated  = $original->withParticipantAdded($this->userId2);

        $this->assertNotSame($original, $updated);
        $this->assertCount(1, $original->getParticipantIds());
        $this->assertCount(2, $updated->getParticipantIds());
        $this->assertContains($this->userId2, $updated->getParticipantIds());
    }

    public function test_with_participant_added_is_idempotent(): void
    {
        $thread  = Thread::create($this->tenantId, ThreadType::Parents, [$this->userId1]);
        $same    = $thread->withParticipantAdded($this->userId1);

        $this->assertSame($thread, $same);
        $this->assertCount(1, $same->getParticipantIds());
    }

    public function test_to_array_contains_expected_keys(): void
    {
        $thread = Thread::create($this->tenantId, ThreadType::Family, [$this->userId1]);
        $arr    = $thread->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('tenant_id', $arr);
        $this->assertArrayHasKey('type', $arr);
        $this->assertArrayHasKey('created_at', $arr);
        $this->assertArrayHasKey('participant_ids', $arr);
        $this->assertSame('family', $arr['type']);
    }

    public function test_from_array_hydrates_correctly(): void
    {
        $now  = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = [
            'id'              => 'd0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'tenant_id'       => $this->tenantId,
            'type'            => 'parents',
            'created_at'      => $now,
            'participant_ids' => [$this->userId1, $this->userId2],
        ];

        $thread = Thread::fromArray($data);

        $this->assertSame('d0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $thread->getId());
        $this->assertSame(ThreadType::Parents, $thread->getType());
        $this->assertCount(2, $thread->getParticipantIds());
    }
}
