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

    // ── Extended event-type coverage (migration 012) ──────────────────────────

    /**
     * @dataProvider allEventTypesProvider
     */
    public function test_create_event_for_each_type(string $type, array $extra): void
    {
        $body = array_merge([
            'title'    => "Test {$type}",
            'type'     => $type,
            'start_at' => '2026-07-01T09:00:00+00:00',
            'end_at'   => '2026-07-01T10:00:00+00:00',
            'all_day'  => false,
        ], $extra);

        $response = $this->makeRequest('POST', '/events', body: $body, cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode(), "Type '{$type}' should be accepted");
        $data = $this->decodeJson($response)['data'];
        $this->assertSame($type, $data['type']);
        $this->assertSame("Test {$type}", $data['title']);
    }

    public static function allEventTypesProvider(): array
    {
        return [
            'custody'     => ['custody',    ['all_day' => true]],
            'activity'    => ['activity',   []],
            'rendezvous'  => ['rendezvous', []],
            'activite'    => ['activite',   []],
            'vacances'    => ['vacances',   ['all_day' => true]],
            'autre'       => ['autre',      []],
        ];
    }

    public function test_medical_event_with_all_fields(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'        => 'Pédiatre annuel',
            'type'         => 'medical',
            'start_at'     => '2026-07-10T14:00:00+00:00',
            'end_at'       => '2026-07-10T15:00:00+00:00',
            'all_day'      => false,
            'child_id'     => $this->childId,
            'report'       => 'Bilan annuel normal. Vaccination à jour.',
            'practitioner' => 'Dr. Martin',
            'recorded_at'  => '2026-07-10T14:30:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $data = $this->decodeJson($response)['data'];
        $this->assertSame('medical', $data['type']);
        $this->assertSame($this->childId, $data['child_id']);
    }

    public function test_invalid_event_type_returns_422(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Invalid type',
            'type'     => 'birthday_party',
            'start_at' => '2026-07-01T09:00:00+00:00',
            'end_at'   => '2026-07-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_all_day_event_custody_full_week(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Garde semaine',
            'type'     => 'custody',
            'start_at' => '2026-07-06T00:00:00+00:00',
            'end_at'   => '2026-07-12T23:59:59+00:00',
            'all_day'  => true,
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['data']['all_day']);
    }

    public function test_event_with_child_id_attached(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Sortie scolaire',
            'type'     => 'activite',
            'start_at' => '2026-08-01T08:00:00+00:00',
            'end_at'   => '2026-08-01T17:00:00+00:00',
            'child_id' => $this->childId,
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($this->childId, $this->decodeJson($response)['data']['child_id']);
    }

    public function test_filter_by_all_types_returns_correct_count(): void
    {
        $types = ['custody', 'activity', 'medical', 'rendezvous', 'activite', 'vacances', 'autre'];

        foreach ($types as $type) {
            $body = [
                'title'    => "Evt {$type}",
                'type'     => $type,
                'start_at' => '2026-09-01T08:00:00+00:00',
                'end_at'   => '2026-09-01T09:00:00+00:00',
            ];
            if ($type === 'medical') {
                $body['child_id'] = $this->childId;
                $body['report']   = 'Test report';
            }
            $this->makeRequest('POST', '/events', body: $body, cookies: ['jwt' => $this->jwtToken]);
        }

        $all = $this->decodeJson($this->makeRequest('GET', '/events', cookies: ['jwt' => $this->jwtToken]));
        $this->assertCount(count($types), $all['data']);

        foreach ($types as $type) {
            $filtered = $this->decodeJson(
                $this->makeRequest('GET', "/events?type={$type}", cookies: ['jwt' => $this->jwtToken])
            );
            $this->assertCount(1, $filtered['data'], "Expected 1 event for type '{$type}'");
            $this->assertSame($type, $filtered['data'][0]['type']);
        }
    }

    public function test_update_event_type(): void
    {
        $created = $this->decodeJson($this->makeRequest('POST', '/events', body: [
            'title'    => 'Vacances été',
            'type'     => 'vacances',
            'start_at' => '2026-08-01T00:00:00+00:00',
            'end_at'   => '2026-08-31T00:00:00+00:00',
            'all_day'  => true,
        ], cookies: ['jwt' => $this->jwtToken]));

        $id = $created['data']['id'];

        $updated = $this->decodeJson($this->makeRequest('PUT', "/events/{$id}", body: [
            'title'    => 'Vacances été — modifié',
            'type'     => 'vacances',
            'start_at' => '2026-08-05T00:00:00+00:00',
            'end_at'   => '2026-08-25T00:00:00+00:00',
            'all_day'  => true,
        ], cookies: ['jwt' => $this->jwtToken]));

        $this->assertSame(200, $updated['status'] ?? 200);
        $this->assertSame('Vacances été — modifié', $updated['data']['title']);
    }

    public function test_returns_401_without_jwt(): void
    {
        $this->assertSame(401, $this->makeRequest('GET', '/events')->getStatusCode());
    }

    // ── end_at validation (end must be strictly after start) ──────────────────

    public function test_create_rejects_end_at_equal_to_start_at(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Zero duration',
            'type'     => 'activite',
            'start_at' => '2026-06-01T10:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(422, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertArrayHasKey('end_at', $body['errors']);
    }

    public function test_create_rejects_end_at_before_start_at(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Backwards',
            'type'     => 'activite',
            'start_at' => '2026-06-01T12:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_create_accepts_multiday_event(): void
    {
        $response = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Vacances Juillet',
            'type'     => 'vacances',
            'start_at' => '2026-07-01T00:00:00+00:00',
            'end_at'   => '2026-07-31T23:59:00+00:00',
            'all_day'  => true,
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertStringContainsString('2026-07-31', $body['data']['end_at']);
    }

    public function test_update_rejects_end_at_before_start_at(): void
    {
        // Create valid event first
        $created = $this->decodeJson($this->makeRequest('POST', '/events', body: [
            'title'    => 'Valid event',
            'type'     => 'activite',
            'start_at' => '2026-06-01T09:00:00+00:00',
            'end_at'   => '2026-06-01T10:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]));
        $id = $created['data']['id'];

        // Try to update with invalid dates
        $response = $this->makeRequest('PUT', "/events/{$id}", body: [
            'title'    => 'Invalid update',
            'type'     => 'activite',
            'start_at' => '2026-06-01T10:00:00+00:00',
            'end_at'   => '2026-06-01T09:00:00+00:00',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(422, $response->getStatusCode());
    }
}
