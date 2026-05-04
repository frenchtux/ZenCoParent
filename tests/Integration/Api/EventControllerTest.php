<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

final class EventControllerTest extends IntegrationTestCase
{
    private string $tenantId;
    private string $userId;
    private string $childId;
    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->createTenant();
        $this->userId   = $this->createUser($this->tenantId);
        $this->childId  = $this->createChild($this->tenantId);

        $jwtService     = new JWTService($_ENV['JWT_SECRET'], 3600);
        $this->jwtToken = $jwtService->generateAccessToken($this->userId, $this->tenantId, 'parent');
    }

    public function test_index_returns_empty_list_initially(): void
    {
        $response = $this->makeRequest('GET', '/events', cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame([], $body['data']);
    }

    public function test_create_activity_event_returns_201(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'School concert',
            'type'     => 'activity',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T11:00:00+00:00',
            'all_day'  => false,
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('School concert', $body['data']['title']);
        $this->assertSame('activity', $body['data']['type']);
    }

    public function test_create_medical_event_requires_report_and_child_id(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Doctor visit',
            'type'     => 'medical',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
            // Missing: child_id and report
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(422, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('errors', $body);
    }

    public function test_create_medical_event_with_report_succeeds(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Doctor visit',
            'type'     => 'medical',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
            'child_id' => $this->childId,
            'report'   => 'Routine checkup. Child is healthy.',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertSame('medical', $body['data']['type']);
        $this->assertSame($this->childId, $body['data']['child_id']);
    }

    public function test_create_event_appears_in_index(): void
    {
        $this->makeRequest('POST', '/events', body: [
            'title'    => 'Activity',
            'type'     => 'activity',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $list = $this->decodeJson(
            $this->makeRequest('GET', '/events', cookies: ['jwt' => $this->jwtToken])
        );
        $this->assertCount(1, $list['data']);
    }

    public function test_show_returns_event_by_id(): void
    {
        $created = $this->decodeJson($this->makeRequest('POST', '/events', body: [
            'title'    => 'My Event',
            'type'     => 'activity',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]));

        $id       = $created['data']['id'];
        $response = $this->makeRequest('GET', "/events/{$id}", cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('My Event', $this->decodeJson($response)['data']['title']);
    }

    public function test_update_event_returns_200(): void
    {
        $created = $this->decodeJson($this->makeRequest('POST', '/events', body: [
            'title'    => 'Original',
            'type'     => 'activity',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]));

        $id = $created['data']['id'];

        $response = $this->makeRequest('PUT', "/events/{$id}", body: [
            'title'    => 'Updated title',
            'type'     => 'activity',
            'start_at' => '2026-06-01T10:00:00+00:00',
            'end_at'   => '2026-06-01T11:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Updated title', $this->decodeJson($response)['data']['title']);
    }

    public function test_delete_event_returns_204(): void
    {
        $created = $this->decodeJson($this->makeRequest('POST', '/events', body: [
            'title'    => 'To delete',
            'type'     => 'activity',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]));

        $id       = $created['data']['id'];
        $response = $this->makeRequest('DELETE', "/events/{$id}", cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(204, $response->getStatusCode());

        // Verify it's gone
        $showResponse = $this->makeRequest('GET', "/events/{$id}", cookies: ['jwt' => $this->jwtToken]);
        $this->assertSame(404, $showResponse->getStatusCode());
    }

    public function test_filter_by_type(): void
    {
        $this->makeRequest('POST', '/events', body: [
            'title' => 'Custody', 'type' => 'custody',
            'start_at' => '2026-06-01T00:00:00+00:00', 'end_at' => '2026-06-07T00:00:00+00:00', 'all_day' => true,
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->makeRequest('POST', '/events', body: [
            'title' => 'Activity', 'type' => 'activity',
            'start_at' => '2026-06-05T09:00:00+00:00', 'end_at' => '2026-06-05T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $custodyOnly = $this->decodeJson(
            $this->makeRequest('GET', '/events?type=custody', cookies: ['jwt' => $this->jwtToken])
        );
        $this->assertCount(1, $custodyOnly['data']);
        $this->assertSame('custody', $custodyOnly['data'][0]['type']);
    }

    public function test_returns_401_without_jwt(): void
    {
        $this->assertSame(401, $this->makeRequest('GET', '/events')->getStatusCode());
    }
}
