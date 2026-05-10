<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\Messaging;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\Messaging\CreateThreadCommand;
use ZenCoParent\Application\Messaging\CreateThreadHandler;
use ZenCoParent\Application\Messaging\ThreadDTO;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;

final class CreateThreadHandlerTest extends TestCase
{
    private MockInterface $threadRepo;
    private MockInterface $userRepo;
    private CreateThreadHandler $handler;

    private string $tenantId = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId1  = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId2  = 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->threadRepo = Mockery::mock(ThreadRepositoryInterface::class);
        $this->userRepo   = Mockery::mock(UserRepositoryInterface::class);
        $this->handler    = new CreateThreadHandler($this->threadRepo, $this->userRepo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_creates_parents_thread_with_explicit_participants(): void
    {
        $user1 = $this->makeUser($this->userId1, UserRole::Parent);
        $user2 = $this->makeUser($this->userId2, UserRole::Parent);

        $this->userRepo->shouldReceive('findById')->with($this->userId1)->andReturn($user1);
        $this->userRepo->shouldReceive('findById')->with($this->userId2)->andReturn($user2);
        $this->threadRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateThreadCommand(
            tenantId:       $this->tenantId,
            type:           'parents',
            createdBy:      $this->userId1,
            participantIds: [$this->userId1, $this->userId2],
        ));

        $this->assertInstanceOf(ThreadDTO::class, $result);
        $this->assertSame('parents', $result->type);
        $this->assertContains($this->userId1, $result->participantIds);
        $this->assertContains($this->userId2, $result->participantIds);
    }

    public function test_creates_family_thread_with_explicit_participants(): void
    {
        $this->threadRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateThreadCommand(
            tenantId:       $this->tenantId,
            type:           'family',
            createdBy:      $this->userId1,
            participantIds: [$this->userId1, $this->userId2],
        ));

        $this->assertSame('family', $result->type);
    }

    public function test_auto_populates_participants_when_none_given(): void
    {
        $user1 = $this->makeUser($this->userId1, UserRole::Parent);
        $user2 = $this->makeUser($this->userId2, UserRole::Parent);

        $this->userRepo->shouldReceive('findByTenantId')->with($this->tenantId)->andReturn([$user1, $user2]);
        $this->userRepo->shouldReceive('findById')->with($this->userId1)->andReturn($user1);
        $this->userRepo->shouldReceive('findById')->with($this->userId2)->andReturn($user2);
        $this->threadRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateThreadCommand(
            tenantId:       $this->tenantId,
            type:           'parents',
            createdBy:      $this->userId1,
            participantIds: [],
        ));

        $this->assertCount(2, $result->participantIds);
    }

    public function test_creator_always_added_to_participants(): void
    {
        $user1 = $this->makeUser($this->userId1, UserRole::Parent);
        $user2 = $this->makeUser($this->userId2, UserRole::Parent);

        $this->userRepo->shouldReceive('findById')->with($this->userId2)->andReturn($user2);
        $this->threadRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateThreadCommand(
            tenantId:       $this->tenantId,
            type:           'parents',
            createdBy:      $this->userId1,
            participantIds: [$this->userId2],
        ));

        $this->assertContains($this->userId1, $result->participantIds);
    }

    public function test_throws_validation_on_invalid_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateThreadCommand(
            tenantId:  $this->tenantId,
            type:      'invalid_type',
            createdBy: $this->userId1,
        ));
    }

    public function test_throws_validation_when_non_parent_in_parents_thread(): void
    {
        $child = $this->makeUser($this->userId2, UserRole::Child);

        $this->userRepo->shouldReceive('findById')->with($this->userId1)->andReturn($this->makeUser($this->userId1, UserRole::Parent));
        $this->userRepo->shouldReceive('findById')->with($this->userId2)->andReturn($child);

        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateThreadCommand(
            tenantId:       $this->tenantId,
            type:           'parents',
            createdBy:      $this->userId1,
            participantIds: [$this->userId1, $this->userId2],
        ));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(string $id, UserRole $role): User
    {
        return User::fromArray([
            'id'                => $id,
            'tenant_id'         => $this->tenantId,
            'email'             => $id . '@example.com',
            'password_hash'     => null,
            'first_name'        => 'Test',
            'last_name'         => 'User',
            'phone'             => null,
            'address'           => null,
            'role'              => $role->value,
            'is_active'         => true,
            'email_verified_at' => null,
            'last_login_at'     => null,
            'created_at'        => '2026-01-01 00:00:00',
            'updated_at'        => '2026-01-01 00:00:00',
        ]);
    }
}
