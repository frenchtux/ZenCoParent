<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

/**
 * Tests for billing endpoints:
 *   GET  /billing/status
 *   POST /payments/checkout/subscription  (parents only)
 */
final class BillingControllerTest extends IntegrationTestCase
{
    private string $tenantId;
    private string $parentId;
    private string $adminId;
    private string $parentJwt;
    private string $adminJwt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId  = $this->createTenant('Test Family', 'test-family');
        $this->parentId  = $this->createUser($this->tenantId, 'parent@example.com', 'Parent99!', 'parent');
        $this->adminId   = $this->createUser($this->tenantId, 'admin@example.com',  'Admin99!',  'admin');

        $jwt = new JWTService($_ENV['JWT_SECRET'], 3600);
        $this->parentJwt = $jwt->generateAccessToken($this->parentId, $this->tenantId, 'parent');
        $this->adminJwt  = $jwt->generateAccessToken($this->adminId,  $this->tenantId, 'admin');
    }

    // ── GET /billing/status ───────────────────────────────────────────────────

    public function test_billing_status_returns_none_when_no_subscription(): void
    {
        $response = $this->makeRequest('GET', '/billing/status', cookies: ['jwt' => $this->parentJwt]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('none', $body['data']['status']);
        $this->assertNull($body['data']['plan']);
    }

    public function test_billing_status_accessible_by_admin_too(): void
    {
        $response = $this->makeRequest('GET', '/billing/status', cookies: ['jwt' => $this->adminJwt]);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_billing_status_requires_auth(): void
    {
        $response = $this->makeRequest('GET', '/billing/status');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_billing_status_shows_trial_subscription(): void
    {
        // Insert a trial subscription
        $subId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $now   = date('Y-m-d H:i:s');
        $end   = date('Y-m-d H:i:s', strtotime('+14 days'));
        $this->pdo->prepare(
            "INSERT INTO subscriptions (id, tenant_id, status, trial_ends_at, created_at, updated_at)
             VALUES (:id, :tid, 'trial', :end, :now, :now)"
        )->execute(['id' => $subId, 'tid' => $this->tenantId, 'end' => $end, 'now' => $now]);

        $response = $this->makeRequest('GET', '/billing/status', cookies: ['jwt' => $this->parentJwt]);

        $body = $this->decodeJson($response);
        $this->assertSame('trial', $body['data']['status']);
        $this->assertNotNull($body['data']['trial_ends_at']);
    }

    // ── POST /payments/checkout/subscription (parent role only) ──────────────

    public function test_checkout_subscription_forbidden_for_admin(): void
    {
        $response = $this->makeRequest(
            'POST',
            '/payments/checkout/subscription',
            body: ['plan_id' => 'fake-plan', 'interval' => 'monthly'],
            cookies: ['jwt' => $this->adminJwt, 'csrf_token' => 'tok'],
            headers: ['X-CSRF-Token' => 'tok'],
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_checkout_subscription_requires_auth(): void
    {
        $response = $this->makeRequest(
            'POST',
            '/payments/checkout/subscription',
            body: ['plan_id' => 'fake', 'interval' => 'monthly'],
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
