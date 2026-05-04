<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

final class UserControllerTest extends IntegrationTestCase
{
    private string $tenantId;
    private string $userId;
    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->createTenant();
        $this->userId   = $this->createUser($this->tenantId, 'alice@example.com', 'Secret123!', 'parent');

        // Generate a valid JWT for this user
        $jwtService    = new JWTService($_ENV['JWT_SECRET'], 3600);
        $this->jwtToken = $jwtService->generateAccessToken($this->userId, $this->tenantId, 'parent');
    }

    public function test_index_returns_200_with_user_list(): void
    {
        $response = $this->makeRequest(
            'GET',
            '/users',
            cookies: ['jwt' => $this->jwtToken],
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['data']);
        $this->assertCount(1, $body['data']);
        $this->assertSame('alice@example.com', $body['data'][0]['email']);
    }

    public function test_index_returns_401_without_jwt(): void
    {
        $response = $this->makeRequest('GET', '/users');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_create_returns_403_for_non_admin(): void
    {
        $response = $this->makeRequest(
            'POST',
            '/users',
            body:    ['email' => 'bob@example.com', 'password' => 'Secret123!', 'first_name' => 'Bob', 'last_name' => 'Test'],
            cookies: ['jwt' => $this->jwtToken],
        );

        // Parent role cannot create users
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_create_returns_201_for_admin(): void
    {
        $adminId  = $this->createUser($this->tenantId, 'admin@example.com', 'Admin123!', 'admin');
        $jwtSvc   = new JWTService($_ENV['JWT_SECRET'], 3600);
        $adminJwt = $jwtSvc->generateAccessToken($adminId, $this->tenantId, 'admin');

        $response = $this->makeRequest(
            'POST',
            '/users',
            body: [
                'email'      => 'bob@example.com',
                'password'   => 'Secret123!',
                'first_name' => 'Bob',
                'last_name'  => 'Test',
                'role'       => 'parent',
            ],
            cookies: ['jwt' => $adminJwt],
        );

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertSame('bob@example.com', $body['data']['email']);
    }

    public function test_create_returns_409_on_duplicate_email(): void
    {
        $adminId  = $this->createUser($this->tenantId, 'admin@example.com', 'Admin123!', 'admin');
        $jwtSvc   = new JWTService($_ENV['JWT_SECRET'], 3600);
        $adminJwt = $jwtSvc->generateAccessToken($adminId, $this->tenantId, 'admin');

        $response = $this->makeRequest(
            'POST',
            '/users',
            body:    ['email' => 'alice@example.com', 'password' => 'Secret123!', 'first_name' => 'Alice2', 'last_name' => 'Test'],
            cookies: ['jwt' => $adminJwt],
        );

        $this->assertSame(409, $response->getStatusCode());
    }
}
