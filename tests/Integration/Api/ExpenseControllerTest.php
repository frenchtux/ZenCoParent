<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

final class ExpenseControllerTest extends IntegrationTestCase
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
        $response = $this->makeRequest('GET', '/expenses', cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame([], $body['data']);
    }

    public function test_create_expense_returns_201(): void
    {
        $response = $this->makeRequest('POST', '/expenses', body: [
            'amount'      => 75.50,
            'description' => 'School lunch',
            'category'    => 'food',
            'date'        => '2026-06-15',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame(75.50, $body['data']['amount']);
        $this->assertSame('School lunch', $body['data']['description']);
        $this->assertSame('food', $body['data']['category']);
        $this->assertSame('2026-06-15', $body['data']['date']);
        $this->assertSame($this->userId, $body['data']['paid_by']);
    }

    public function test_create_returns_400_when_amount_missing(): void
    {
        $response = $this->makeRequest('POST', '/expenses', body: [
            'description' => 'Test',
            'date'        => '2026-06-15',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_create_returns_400_when_date_missing(): void
    {
        $response = $this->makeRequest('POST', '/expenses', body: [
            'amount'      => 50.0,
            'description' => 'Test',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_create_returns_422_for_invalid_amount(): void
    {
        $response = $this->makeRequest('POST', '/expenses', body: [
            'amount'      => -10,
            'description' => 'Negative',
            'date'        => '2026-06-15',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_expense_appears_in_index_after_creation(): void
    {
        $this->makeRequest('POST', '/expenses', body: [
            'amount'      => 30.0,
            'description' => 'Taxi',
            'date'        => '2026-06-10',
        ], cookies: ['jwt' => $this->jwtToken]);

        $list = $this->decodeJson(
            $this->makeRequest('GET', '/expenses', cookies: ['jwt' => $this->jwtToken])
        );
        $this->assertCount(1, $list['data']);
    }

    public function test_create_expense_with_split_ratio(): void
    {
        $userId2 = $this->createUser($this->tenantId, 'bob@example.com');

        $response = $this->makeRequest('POST', '/expenses', body: [
            'amount'      => 100.0,
            'description' => 'Groceries',
            'date'        => '2026-06-15',
            'split_ratio' => [$this->userId => 60, $userId2 => 40],
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertCount(2, $body['data']['split_ratio']);
    }

    public function test_update_expense_returns_200(): void
    {
        $id = $this->createExpense($this->tenantId, $this->userId);

        $response = $this->makeRequest('PUT', "/expenses/{$id}", body: [
            'amount'      => 99.0,
            'description' => 'Updated description',
            'date'        => '2026-06-20',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertSame(99.0, $body['data']['amount']);
        $this->assertSame('Updated description', $body['data']['description']);
    }

    public function test_update_returns_404_for_unknown_expense(): void
    {
        $response = $this->makeRequest('PUT', '/expenses/f0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', body: [
            'amount'      => 50.0,
            'description' => 'Ghost',
            'date'        => '2026-06-15',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_delete_expense_returns_204(): void
    {
        $id = $this->createExpense($this->tenantId, $this->userId);

        $response = $this->makeRequest('DELETE', "/expenses/{$id}", cookies: ['jwt' => $this->jwtToken]);
        $this->assertSame(204, $response->getStatusCode());

        $list = $this->decodeJson(
            $this->makeRequest('GET', '/expenses', cookies: ['jwt' => $this->jwtToken])
        );
        $this->assertCount(0, $list['data']);
    }

    public function test_filter_by_category(): void
    {
        $this->makeRequest('POST', '/expenses', body: [
            'amount'      => 50.0,
            'description' => 'Lunch',
            'category'    => 'food',
            'date'        => '2026-06-01',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->makeRequest('POST', '/expenses', body: [
            'amount'      => 30.0,
            'description' => 'Books',
            'category'    => 'education',
            'date'        => '2026-06-02',
        ], cookies: ['jwt' => $this->jwtToken]);

        $food = $this->decodeJson(
            $this->makeRequest('GET', '/expenses?category=food', cookies: ['jwt' => $this->jwtToken])
        );
        $this->assertCount(1, $food['data']);
        $this->assertSame('food', $food['data'][0]['category']);
    }

    public function test_returns_401_without_jwt(): void
    {
        $this->assertSame(401, $this->makeRequest('GET', '/expenses')->getStatusCode());
    }
}
