<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

final class ChildControllerTest extends IntegrationTestCase
{
    private string $tenantId;
    private string $userId;
    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->createTenant();
        $this->userId   = $this->createUser($this->tenantId);

        $jwtService     = new JWTService($_ENV['JWT_SECRET'], 3600);
        $this->jwtToken = $jwtService->generateAccessToken($this->userId, $this->tenantId, 'parent');
    }

    public function test_index_returns_empty_list_initially(): void
    {
        $response = $this->makeRequest('GET', '/children', cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame([], $body['data']);
    }

    public function test_create_returns_201_with_child_data(): void
    {
        $response = $this->makeRequest(
            'POST',
            '/children',
            body: [
                'first_name' => 'Emma',
                'last_name'  => 'Test',
                'birthdate'  => '2015-06-15',
            ],
            cookies: ['jwt' => $this->jwtToken],
        );

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('Emma', $body['data']['first_name']);
        $this->assertSame('Test', $body['data']['last_name']);
        $this->assertSame('2015-06-15', $body['data']['birthdate']);
    }

    public function test_create_returns_400_when_first_name_missing(): void
    {
        $response = $this->makeRequest(
            'POST',
            '/children',
            body:    ['last_name' => 'Test'],
            cookies: ['jwt' => $this->jwtToken],
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_index_returns_401_without_jwt(): void
    {
        $response = $this->makeRequest('GET', '/children');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_child_appears_in_list_after_creation(): void
    {
        $this->makeRequest(
            'POST',
            '/children',
            body:    ['first_name' => 'Emma', 'last_name' => 'Test'],
            cookies: ['jwt' => $this->jwtToken],
        );

        $listResponse = $this->makeRequest('GET', '/children', cookies: ['jwt' => $this->jwtToken]);
        $body         = $this->decodeJson($listResponse);

        $this->assertCount(1, $body['data']);
        $this->assertSame('Emma', $body['data'][0]['first_name']);
    }
}
