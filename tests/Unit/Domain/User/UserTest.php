<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\User;

use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRole;

final class UserTest extends TestCase
{
    private string $tenantId  = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $email     = 'alice@example.com';
    private string $firstName = 'Alice';
    private string $lastName  = 'Dupont';
    private string $hash      = '$2y$12$placeholder_hash_value_here_x';

    public function test_create_builds_user_with_defaults(): void
    {
        $user = User::create($this->tenantId, $this->email, $this->hash, $this->firstName, $this->lastName);

        $this->assertNotEmpty($user->getId());
        $this->assertSame($this->tenantId, $user->getTenantId());
        $this->assertSame($this->email, $user->getEmail());
        $this->assertSame($this->firstName, $user->getFirstName());
        $this->assertSame($this->lastName, $user->getLastName());
        $this->assertSame(UserRole::Parent, $user->getRole());
        $this->assertTrue($user->isActive());
        $this->assertNull($user->getPhone());
        $this->assertNull($user->getEmailVerifiedAt());
    }

    public function test_get_full_name_concatenates_first_and_last(): void
    {
        $user = User::create($this->tenantId, $this->email, $this->hash, $this->firstName, $this->lastName);
        $this->assertSame('Alice Dupont', $user->getFullName());
    }

    public function test_with_updated_profile_returns_new_instance(): void
    {
        $original = User::create($this->tenantId, $this->email, $this->hash, $this->firstName, $this->lastName);
        $updated  = $original->withUpdatedProfile('Bob', 'Martin', '+33123456789', '12 rue de la Paix');

        $this->assertSame('Bob', $updated->getFirstName());
        $this->assertSame('Martin', $updated->getLastName());
        $this->assertSame('+33123456789', $updated->getPhone());
        // Original unchanged (immutability)
        $this->assertSame('Alice', $original->getFirstName());
    }

    public function test_with_last_login_sets_timestamp(): void
    {
        $before = new \DateTimeImmutable();
        $user   = User::create($this->tenantId, $this->email, $this->hash, $this->firstName, $this->lastName);
        $after  = $user->withLastLogin();

        $this->assertNull($user->getLastLoginAt());
        $this->assertNotNull($after->getLastLoginAt());
        $this->assertGreaterThanOrEqual($before, $after->getLastLoginAt());
    }

    public function test_to_public_array_excludes_password_hash(): void
    {
        $user = User::create($this->tenantId, $this->email, $this->hash, $this->firstName, $this->lastName);
        $arr  = $user->toPublicArray();

        $this->assertArrayNotHasKey('password_hash', $arr);
        $this->assertSame($this->email, $arr['email']);
    }

    public function test_from_array_hydrates_entity(): void
    {
        $now  = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = [
            'id'                => 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'tenant_id'         => $this->tenantId,
            'email'             => $this->email,
            'password_hash'     => $this->hash,
            'first_name'        => $this->firstName,
            'last_name'         => $this->lastName,
            'phone'             => null,
            'address'           => null,
            'role'              => 'parent',
            'is_active'         => true,
            'email_verified_at' => null,
            'last_login_at'     => null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        $user = User::fromArray($data);

        $this->assertSame('b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $user->getId());
        $this->assertSame(UserRole::Parent, $user->getRole());
    }

    public function test_with_email_verified_sets_timestamp(): void
    {
        $user     = User::create($this->tenantId, $this->email, $this->hash, $this->firstName, $this->lastName);
        $verified = $user->withEmailVerified();

        $this->assertNull($user->getEmailVerifiedAt());
        $this->assertNotNull($verified->getEmailVerifiedAt());
    }
}
