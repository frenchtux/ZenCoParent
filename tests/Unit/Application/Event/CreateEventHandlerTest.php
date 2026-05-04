<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\Event;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\Event\CreateEventCommand;
use ZenCoParent\Application\Event\CreateEventHandler;
use ZenCoParent\Application\Event\EventDTO;
use ZenCoParent\Domain\Child\Child;
use ZenCoParent\Domain\Child\ChildRepositoryInterface;
use ZenCoParent\Domain\Event\EventRepositoryInterface;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\Shared\TransactionManagerInterface;

final class CreateEventHandlerTest extends TestCase
{
    private MockInterface $eventRepo;
    private MockInterface $medicalRepo;
    private MockInterface $childRepo;
    private MockInterface $txManager;
    private CreateEventHandler $handler;

    private string $tenantId  = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId    = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $childId   = 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->eventRepo   = Mockery::mock(EventRepositoryInterface::class);
        $this->medicalRepo = Mockery::mock(MedicalRecordRepositoryInterface::class);
        $this->childRepo   = Mockery::mock(ChildRepositoryInterface::class);
        $this->txManager   = Mockery::mock(TransactionManagerInterface::class);

        $this->handler = new CreateEventHandler(
            $this->eventRepo,
            $this->medicalRepo,
            $this->childRepo,
            $this->txManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_creates_activity_event_without_report(): void
    {
        $this->txManager->shouldReceive('begin')->once();
        $this->txManager->shouldReceive('commit')->once();
        $this->eventRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateEventCommand(
            tenantId:  $this->tenantId,
            title:     'School activity',
            type:      'activity',
            startAt:   '2026-06-01T09:00:00+00:00',
            endAt:     '2026-06-01T10:00:00+00:00',
            allDay:    false,
            createdBy: $this->userId,
        ));

        $this->assertInstanceOf(EventDTO::class, $result);
        $this->assertSame('School activity', $result->title);
        $this->assertSame('activity', $result->type);
    }

    public function test_creates_medical_event_with_report_and_child(): void
    {
        $child = $this->makeChild();
        $this->childRepo->shouldReceive('findById')->with($this->childId)->andReturn($child);
        $this->txManager->shouldReceive('begin')->once();
        $this->txManager->shouldReceive('commit')->once();
        $this->eventRepo->shouldReceive('save')->once();
        $this->medicalRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new CreateEventCommand(
            tenantId:  $this->tenantId,
            title:     'Doctor visit',
            type:      'medical',
            startAt:   '2026-06-01T09:00:00+00:00',
            endAt:     '2026-06-01T10:00:00+00:00',
            allDay:    false,
            createdBy: $this->userId,
            childId:   $this->childId,
            report:    'Routine checkup, all clear.',
        ));

        $this->assertSame('medical', $result->type);
    }

    public function test_throws_validation_if_medical_event_missing_report(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateEventCommand(
            tenantId:  $this->tenantId,
            title:     'Doctor visit',
            type:      'medical',
            startAt:   '2026-06-01T09:00:00+00:00',
            endAt:     '2026-06-01T10:00:00+00:00',
            allDay:    false,
            createdBy: $this->userId,
            childId:   $this->childId,
            report:    null, // missing!
        ));
    }

    public function test_throws_validation_if_medical_event_missing_child_id(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateEventCommand(
            tenantId:  $this->tenantId,
            title:     'Doctor visit',
            type:      'medical',
            startAt:   '2026-06-01T09:00:00+00:00',
            endAt:     '2026-06-01T10:00:00+00:00',
            allDay:    false,
            createdBy: $this->userId,
            childId:   null, // missing!
            report:    'Some report',
        ));
    }

    public function test_throws_validation_on_invalid_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateEventCommand(
            tenantId:  $this->tenantId,
            title:     'Bad event',
            type:      'invalid_type',
            startAt:   '2026-06-01T09:00:00+00:00',
            endAt:     '2026-06-01T10:00:00+00:00',
            allDay:    false,
            createdBy: $this->userId,
        ));
    }

    public function test_throws_validation_when_end_before_start(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateEventCommand(
            tenantId:  $this->tenantId,
            title:     'Backwards event',
            type:      'activity',
            startAt:   '2026-06-01T10:00:00+00:00',
            endAt:     '2026-06-01T09:00:00+00:00', // before start!
            allDay:    false,
            createdBy: $this->userId,
        ));
    }

    public function test_rollback_on_repository_failure(): void
    {
        $this->txManager->shouldReceive('begin')->once();
        $this->txManager->shouldReceive('rollback')->once();
        $this->eventRepo->shouldReceive('save')->andThrow(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);

        $this->handler->handle(new CreateEventCommand(
            tenantId:  $this->tenantId,
            title:     'Test event',
            type:      'activity',
            startAt:   '2026-06-01T09:00:00+00:00',
            endAt:     '2026-06-01T10:00:00+00:00',
            allDay:    false,
            createdBy: $this->userId,
        ));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeChild(): Child
    {
        return Child::create($this->tenantId, 'Emma', 'Test', '2015-06-15', $this->userId);
    }
}
