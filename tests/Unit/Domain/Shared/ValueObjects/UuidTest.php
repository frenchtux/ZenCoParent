<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\Shared\ValueObjects\Uuid;

final class UuidTest extends TestCase
{
    public function test_generate_produces_valid_uuid(): void
    {
        $uuid = Uuid::generate();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->toString(),
        );
    }

    public function test_generate_produces_unique_values(): void
    {
        $a = Uuid::generate();
        $b = Uuid::generate();
        $this->assertFalse($a->equals($b));
    }

    public function test_from_string_accepts_valid_uuid(): void
    {
        $raw  = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = Uuid::fromString($raw);
        $this->assertSame($raw, $uuid->toString());
    }

    public function test_from_string_throws_on_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uuid::fromString('not-a-uuid');
    }

    public function test_to_string_returns_value(): void
    {
        $raw  = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = Uuid::fromString($raw);
        $this->assertSame($raw, (string) $uuid);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $raw = '550e8400-e29b-41d4-a716-446655440000';
        $a   = Uuid::fromString($raw);
        $b   = Uuid::fromString($raw);
        $this->assertTrue($a->equals($b));
    }
}
