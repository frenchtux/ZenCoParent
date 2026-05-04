<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\User;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\User\CreateUserCommand;
use ZenCoParent\Application\User\CreateUserHandler;
use ZenCoParent\Application\User\UserDTO;
use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\User\Exception\UserAlreadyExistsException;
use ZenCoParent\Domain\User\UserRepositoryInterface;

final class CreateUserHandlerTest extends TestCase
{
    private MockInterface     $userRepo;
    private CreateUserHandler $handler;
    private string $tenantId = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->handler  = new CreateUserHandler($this->userRepo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_creates_user_successfully(): void
    {
        $this->userRepo->shouldReceive('existsByEmail')
            ->with($this->tenantId, 'alice@example.com')
            ->andReturn(false);

        $this->userRepo->shouldReceive('save')->once();

        $command = new CreateUserCommand(
            tenantId:  $this->tenantId,
            email:     'alice@example.com',
            password:  'Secret123!',
            firstName: 'Alice',
            lastName:  'Dupont',
        );

        $dto = $this->handler->handle($command);

        $this->assertInstanceOf(UserDTO::class, $dto);
        $this->assertSame('alice@example.com', $dto->email);
        $this->assertSame('Alice', $dto->firstName);
    }

    public function test_throws_validation_exception_on_invalid_email(): void
    {
        $command = new CreateUserCommand(
            tenantId:  $this->tenantId,
            email:     'not-an-email',
            password:  'Secret123!',
            firstName: 'Alice',
            lastName:  'Dupont',
        );

        $this->expectException(ValidationException::class);
        $this->handler->handle($command);
    }

    public function test_throws_validation_exception_on_short_password(): void
    {
        $command = new CreateUserCommand(
            tenantId:  $this->tenantId,
            email:     'alice@example.com',
            password:  'short',
            firstName: 'Alice',
            lastName:  'Dupont',
        );

        $this->userRepo->shouldReceive('existsByEmail')->andReturn(false);
        $this->expectException(ValidationException::class);
        $this->handler->handle($command);
    }

    public function test_throws_when_email_already_exists(): void
    {
        $this->userRepo->shouldReceive('existsByEmail')
            ->with($this->tenantId, 'alice@example.com')
            ->andReturn(true);

        $command = new CreateUserCommand(
            tenantId:  $this->tenantId,
            email:     'alice@example.com',
            password:  'Secret123!',
            firstName: 'Alice',
            lastName:  'Dupont',
        );

        $this->expectException(UserAlreadyExistsException::class);
        $this->handler->handle($command);
    }

    public function test_password_is_hashed_before_saving(): void
    {
        $this->userRepo->shouldReceive('existsByEmail')->andReturn(false);
        $this->userRepo->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function ($user) {
                // Verify password is bcrypt-hashed (not plain text)
                return $user->getPasswordHash() !== 'Secret123!'
                    && str_starts_with($user->getPasswordHash(), '$2y$');
            }));

        $this->handler->handle(new CreateUserCommand(
            tenantId:  $this->tenantId,
            email:     'alice@example.com',
            password:  'Secret123!',
            firstName: 'Alice',
            lastName:  'Dupont',
        ));
    }
}
