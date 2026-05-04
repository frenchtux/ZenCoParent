<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

final class MedicalRecordControllerTest extends IntegrationTestCase
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

    public function test_create_standalone_medical_record_returns_201(): void
    {
        $response = $this->makeRequest('POST', '/medical-records', body: [
            'child_id'    => $this->childId,
            'report'      => 'Child had a mild fever. Prescribed paracetamol.',
            'practitioner'=> 'Dr. Martin',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame($this->childId, $body['data']['child_id']);
        $this->assertSame('Dr. Martin', $body['data']['practitioner']);
    }

    public function test_create_returns_400_when_report_missing(): void
    {
        $response = $this->makeRequest('POST', '/medical-records', body: [
            'child_id' => $this->childId,
            // report missing
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_create_returns_400_when_child_id_missing(): void
    {
        $response = $this->makeRequest('POST', '/medical-records', body: [
            'report' => 'Some report',
            // child_id missing
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_create_returns_404_when_child_not_in_tenant(): void
    {
        $response = $this->makeRequest('POST', '/medical-records', body: [
            'child_id' => 'f0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'report'   => 'Report for unknown child',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_child_history_returns_records_for_child(): void
    {
        // Create 2 records
        $this->makeRequest('POST', '/medical-records', body: [
            'child_id' => $this->childId,
            'report'   => 'First visit',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->makeRequest('POST', '/medical-records', body: [
            'child_id' => $this->childId,
            'report'   => 'Second visit',
        ], cookies: ['jwt' => $this->jwtToken]);

        $response = $this->makeRequest(
            'GET',
            "/children/{$this->childId}/medical-history",
            cookies: ['jwt' => $this->jwtToken],
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertCount(2, $body['data']);
    }

    public function test_child_history_returns_404_for_unknown_child(): void
    {
        $response = $this->makeRequest(
            'GET',
            '/children/f0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11/medical-history',
            cookies: ['jwt' => $this->jwtToken],
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_medical_record_created_with_medical_event_is_linked(): void
    {
        // Create a medical event → auto-creates a linked medical record
        $eventResponse = $this->makeRequest('POST', '/events', body: [
            'title'    => 'Doctor appointment',
            'type'     => 'medical',
            'start_at' => '2026-06-10T09:00:00+00:00',
            'end_at'   => '2026-06-10T10:00:00+00:00',
            'child_id' => $this->childId,
            'report'   => 'Annual checkup completed. No issues found.',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $eventResponse->getStatusCode());

        // Child medical history should have 1 entry (the auto-created record)
        $historyResponse = $this->makeRequest(
            'GET',
            "/children/{$this->childId}/medical-history",
            cookies: ['jwt' => $this->jwtToken],
        );

        $body = $this->decodeJson($historyResponse);
        $this->assertCount(1, $body['data']);
        $this->assertSame('Annual checkup completed. No issues found.', $body['data'][0]['report']);
    }
}
