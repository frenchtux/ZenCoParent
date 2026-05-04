<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\Child;

use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\Child\Child;

final class ChildTest extends TestCase
{
    private string $tenantId  = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $createdBy = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    public function test_create_builds_child_with_defaults(): void
    {
        $child = Child::create($this->tenantId, 'Emma', 'Dupont', '2015-06-15', $this->createdBy);

        $this->assertNotEmpty($child->getId());
        $this->assertSame($this->tenantId, $child->getTenantId());
        $this->assertSame('Emma', $child->getFirstName());
        $this->assertSame('Dupont', $child->getLastName());
        $this->assertNotNull($child->getBirthdate());
        $this->assertSame([], $child->getMedicalInfo());
        $this->assertSame([], $child->getSchoolInfo());
    }

    public function test_create_without_birthdate(): void
    {
        $child = Child::create($this->tenantId, 'Tom', 'Martin', null, $this->createdBy);
        $this->assertNull($child->getBirthdate());
    }

    public function test_with_updated_info_returns_new_instance(): void
    {
        $original = Child::create($this->tenantId, 'Emma', 'Dupont', '2015-06-15', $this->createdBy);
        $updated  = $original->withUpdatedInfo(
            'Emma',
            'Dupont-Martin',
            '2015-06-15',
            ['allergies' => ['peanuts']],
            ['school' => 'École Jules Ferry'],
        );

        $this->assertSame('Dupont-Martin', $updated->getLastName());
        $this->assertSame(['allergies' => ['peanuts']], $updated->getMedicalInfo());
        // Original unchanged
        $this->assertSame('Dupont', $original->getLastName());
    }

    public function test_from_array_decodes_json_fields(): void
    {
        $now  = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = [
            'id'           => 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'tenant_id'    => $this->tenantId,
            'first_name'   => 'Emma',
            'last_name'    => 'Dupont',
            'birthdate'    => '2015-06-15',
            'medical_info' => '{"allergies":["peanuts"]}',
            'school_info'  => '{"school":"Jules Ferry"}',
            'created_by'   => $this->createdBy,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        $child = Child::fromArray($data);

        $this->assertSame(['allergies' => ['peanuts']], $child->getMedicalInfo());
        $this->assertSame(['school' => 'Jules Ferry'], $child->getSchoolInfo());
    }

    public function test_to_array_json_encodes_fields(): void
    {
        $child = Child::create($this->tenantId, 'Emma', 'Dupont', '2015-06-15', $this->createdBy);
        $arr   = $child->toArray();

        $this->assertArrayHasKey('medical_info', $arr);
        $this->assertArrayHasKey('school_info', $arr);
    }
}
