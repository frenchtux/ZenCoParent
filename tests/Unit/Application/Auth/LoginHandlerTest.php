<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\Auth;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\Auth\LoginCommand;
use ZenCoParent\Application\Auth\LoginHandler;
use ZenCoParent\Application\Auth\LoginResult;
use ZenCoParent\Domain\Auth\RefreshTokenRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\Tenant\Tenant;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\User;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserRole;
use ZenCoParent\Infrastructure\Auth\JWTService;

final class LoginHandlerTest extends TestCase
{
    private MockInterface $userRepo;
    private MockInterface $tenantRepo;
    private MockInterface $refreshRepo;
    private MockInterface $jwt;
    private LoginHandler  $handler;

    private string $tenantId = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId   = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->userRepo    = Mockery::mock(UserRepositoryInterface::class);
        $this->tenantRepo  = Mockery::mock(TenantRepositoryInterface::class);
        $this->refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
        $this->jwt         = Mockery::mock(JWTService::class);

        $this->handler = new LoginHandler(
            $this->userRepo,
            $this->tenantRepo,
            $this->refreshRepo,
            $this->jwt,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_login_returns_result_with_tokens_on_success(): void
    {
        $password = 'Secret123!';
        $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);

        $tenant = $this->makeTenant();
        $user   = User::create($this->tenantId, 'alice@example.com', $hash, 'Alice', 'Dupont');

        $this->tenantRepo->shouldReceive('findBySlug')
            ->with('dupont-family')
            ->andReturn($tenant);

        $this->userRepo->shouldReceive('findByEmail')
            ->with($this->tenantId, 'alice@example.com')
            ->andReturn($user);

        $this->userRepo->shouldReceive('update')->once();

        $this->jwt->shouldReceive('generateAccessToken')
            ->once()
            ->andReturn('access-token');

        $this->jwt->shouldReceive('generateRefreshToken')
            ->once()
            ->andReturn('refresh-token');

        $this->jwt->shouldReceive('hashRefreshToken')
            ->with('refresh-token')
            ->andReturn('hashed-refresh-token');

        $this->refreshRepo->shouldReceive('save')
            ->once()
            ->with($user->getId(), 'hashed-refresh-token', Mockery::type(\DateTimeImmutable::class));

        $result = $this->handler->handle(new LoginCommand('alice@example.com', $password, 'dupont-family'));

        $this->assertInstanceOf(LoginResult::class, $result);
        $this->assertSame('access-token', $result->accessToken);
        $this->assertSame('refresh-token', $result->refreshToken);
        $this->assertSame('alice@example.com', $result->user->email);
    }

    public function test_login_throws_when_tenant_not_found(): void
    {
        $this->tenantRepo->shouldReceive('findBySlug')->andReturn(null);
        $this->expectException(NotFoundException::class);
        $this->handler->handle(new LoginCommand('a@b.com', 'pass', 'unknown'));
    }

    public function test_login_throws_when_user_not_found(): void
    {
        $this->tenantRepo->shouldReceive('findBySlug')->andReturn($this->makeTenant());
        $this->userRepo->shouldReceive('findByEmail')->andReturn(null);
        $this->expectException(UnauthorizedException::class);
        $this->handler->handle(new LoginCommand('missing@b.com', 'pass', 'dupont-family'));
    }

    public function test_login_throws_on_wrong_password(): void
    {
        $hash = password_hash('correct', PASSWORD_BCRYPT, ['cost' => 4]);
        $user = User::create($this->tenantId, 'alice@example.com', $hash, 'Alice', 'Dupont');

        $this->tenantRepo->shouldReceive('findBySlug')->andReturn($this->makeTenant());
        $this->userRepo->shouldReceive('findByEmail')->andReturn($user);

        $this->expectException(UnauthorizedException::class);
        $this->handler->handle(new LoginCommand('alice@example.com', 'wrong', 'dupont-family'));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeTenant(): Tenant
    {
        $now = new \DateTimeImmutable();
        return new Tenant(
            id:              $this->tenantId,
            name:            'Dupont Family',
            slug:            'dupont-family',
            isActive:        true,
            modulesOverride: null,
            createdAt:       $now,
            updatedAt:       $now,
        );
    }
}
