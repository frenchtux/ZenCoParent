<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\Shared\ValueObjects\Email;

final class EmailTest extends TestCase
{
    public function test_from_string_accepts_valid_email(): void
    {
        $email = Email::fromString('User@Example.COM');
        $this->assertSame('user@example.com', $email->toString());
    }

    public function test_from_string_throws_on_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('not-an-email');
    }

    public function test_from_string_throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('');
    }

    public function test_equals_is_case_insensitive(): void
    {
        $a = Email::fromString('user@example.com');
        $b = Email::fromString('USER@EXAMPLE.COM');
        $this->assertTrue($a->equals($b));
    }

    public function test_to_string_returns_lowercase_email(): void
    {
        $email = Email::fromString('Test@Domain.org');
        $this->assertSame('test@domain.org', (string) $email);
    }
}
