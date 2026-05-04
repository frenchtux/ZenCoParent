<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\Event;

use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\Event\Event;
use ZenCoParent\Domain\Event\EventType;

final class EventTest extends TestCase
{
    private string $tenantId  = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $createdBy = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    public function test_create_builds_event_with_required_fields(): void
    {
        $start = new \DateTimeImmutable('2026-06-01 09:00:00');
        $end   = new \DateTimeImmutable('2026-06-01 10:00:00');

        $event = Event::create($this->tenantId, 'Doctor visit', EventType::Medical, $start, $end, false, $this->createdBy);

        $this->assertNotEmpty($event->getId());
        $this->assertSame($this->tenantId, $event->getTenantId());
        $this->assertSame('Doctor visit', $event->getTitle());
        $this->assertSame(EventType::Medical, $event->getType());
        $this->assertSame($start, $event->getStartAt());
        $this->assertSame($end, $event->getEndAt());
        $this->assertFalse($event->isAllDay());
        $this->assertNull($event->getChildId());
        $this->assertNull($event->getDescription());
    }

    public function test_create_accepts_optional_fields(): void
    {
        $childId = 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
        $start   = new \DateTimeImmutable('2026-06-01');
        $end     = new \DateTimeImmutable('2026-06-02');

        $event = Event::create(
            $this->tenantId,
            'Custody week',
            EventType::Custody,
            $start,
            $end,
            true,
            $this->createdBy,
            childId:     $childId,
            description: 'Week with parent A',
        );

        $this->assertSame($childId, $event->getChildId());
        $this->assertSame('Week with parent A', $event->getDescription());
        $this->assertTrue($event->isAllDay());
    }

    public function test_with_updated_returns_new_instance(): void
    {
        $start   = new \DateTimeImmutable('2026-06-01 09:00:00');
        $end     = new \DateTimeImmutable('2026-06-01 10:00:00');
        $original = Event::create($this->tenantId, 'Old title', EventType::Activity, $start, $end, false, $this->createdBy);

        $newStart = new \DateTimeImmutable('2026-06-01 10:00:00');
        $newEnd   = new \DateTimeImmutable('2026-06-01 11:00:00');
        $updated  = $original->withUpdated('New title', 'New desc', EventType::Activity, $newStart, $newEnd, false, null);

        $this->assertSame('New title', $updated->getTitle());
        $this->assertSame('New desc', $updated->getDescription());
        $this->assertSame($newStart, $updated->getStartAt());
        // Original unchanged
        $this->assertSame('Old title', $original->getTitle());
        // updatedAt was refreshed
        $this->assertGreaterThanOrEqual($original->getUpdatedAt(), $updated->getUpdatedAt());
    }

    public function test_to_array_formats_dates_as_atom(): void
    {
        $start = new \DateTimeImmutable('2026-06-01T09:00:00+00:00');
        $end   = new \DateTimeImmutable('2026-06-01T10:00:00+00:00');
        $event = Event::create($this->tenantId, 'Test', EventType::Activity, $start, $end, false, $this->createdBy);
        $arr   = $event->toArray();

        $this->assertSame('activity', $arr['type']);
        $this->assertStringContainsString('T', $arr['start_at']);
        $this->assertStringContainsString('T', $arr['end_at']);
    }

    public function test_from_array_hydrates_correctly(): void
    {
        $now  = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = [
            'id'          => 'd0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'tenant_id'   => $this->tenantId,
            'child_id'    => null,
            'title'       => 'Hydrated event',
            'description' => null,
            'type'        => 'custody',
            'start_at'    => $now,
            'end_at'      => $now,
            'all_day'     => false,
            'created_by'  => $this->createdBy,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        $event = Event::fromArray($data);

        $this->assertSame('Hydrated event', $event->getTitle());
        $this->assertSame(EventType::Custody, $event->getType());
        $this->assertNull($event->getChildId());
    }
}
