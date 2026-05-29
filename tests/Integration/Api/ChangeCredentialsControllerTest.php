<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

/**
 * Tests for PATCH /users/me/credentials
 * and must_change_credentials flag propagation through login.
 */
final class ChangeCredentialsControllerTest extends IntegrationTestCase
{
    private string $tenantId;
    private string $userId;
    private string $jwt;
    private string $csrf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->createTenant('Test Family', 'test-family');
        $this->userId   = $this->createUser(
            $this->tenantId,
            'admin@example.com',
            'OldPass99!',
            'admin',
            mustChangeCredentials: true,
        );
        [$this->jwt, $this->csrf] = $this->loginAs('admin@example.com', 'OldPass99!');
    }

    // ── Login returns must_change_credentials flag ───────────────────────────

    public function test_login_exposes_must_change_credentials_flag(): void
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'admin@example.com',
            'password'    => 'OldPass99!',
            'tenant_slug' => 'test-family',
        ]);

        $body = $this->decodeJson($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['data']['user']['must_change_credentials']);
    }

    public function test_login_flag_is_false_for_normal_user(): void
    {
        $this->createUser($this->tenantId, 'parent@example.com', 'Parent99!', 'parent', false);

        $response = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'parent@example.com',
            'password'    => 'Parent99!',
            'tenant_slug' => 'test-family',
        ]);

        $body = $this->decodeJson($response);
        $this->assertFalse($body['data']['user']['must_change_credentials']);
    }

    // ── PATCH /users/me/credentials ──────────────────────────────────────────

    public function test_change_credentials_succeeds_with_valid_data(): void
    {
        $response = $this->makeRequest(
            'PATCH',
            '/users/me/credentials',
            [
                'current_password' => 'OldPass99!',
                'new_email'        => 'newemail@example.com',
                'new_password'     => 'NewPass99!',
            ],
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            headers: ['X-CSRF-Token' => $this->csrf],
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('newemail@example.com', $body['data']['email']);
        $this->assertFalse($body['data']['must_change_credentials']);
    }

    public function test_change_credentials_rejects_wrong_current_password(): void
    {
        $response = $this->makeRequest(
            'PATCH',
            '/users/me/credentials',
            [
                'current_password' => 'WrongPass!!',
                'new_email'        => 'new@example.com',
                'new_password'     => 'NewPass99!',
            ],
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            headers: ['X-CSRF-Token' => $this->csrf],
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_change_credentials_rejects_short_password(): void
    {
        $response = $this->makeRequest(
            'PATCH',
            '/users/me/credentials',
            [
                'current_password' => 'OldPass99!',
                'new_email'        => 'new@example.com',
                'new_password'     => 'short',
            ],
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            headers: ['X-CSRF-Token' => $this->csrf],
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_change_credentials_rejects_invalid_email(): void
    {
        $response = $this->makeRequest(
            'PATCH',
            '/users/me/credentials',
            [
                'current_password' => 'OldPass99!',
                'new_email'        => 'not-an-email',
                'new_password'     => 'NewPass99!',
            ],
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            headers: ['X-CSRF-Token' => $this->csrf],
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_change_credentials_requires_authentication(): void
    {
        $response = $this->makeRequest('PATCH', '/users/me/credentials', [
            'current_password' => 'OldPass99!',
            'new_email'        => 'new@example.com',
            'new_password'     => 'NewPass99!',
        ]);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_flag_resets_after_successful_credentials_change(): void
    {
        // Change credentials
        $this->makeRequest(
            'PATCH',
            '/users/me/credentials',
            [
                'current_password' => 'OldPass99!',
                'new_email'        => 'changed@example.com',
                'new_password'     => 'NewPass99!',
            ],
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            headers: ['X-CSRF-Token' => $this->csrf],
        );

        // Login again with new credentials
        $loginResponse = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'changed@example.com',
            'password'    => 'NewPass99!',
            'tenant_slug' => 'test-family',
        ]);

        $body = $this->decodeJson($loginResponse);
        $this->assertSame(200, $loginResponse->getStatusCode());
        $this->assertFalse($body['data']['user']['must_change_credentials']);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function loginAs(string $email, string $password): array
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email'       => $email,
            'password'    => $password,
            'tenant_slug' => 'test-family',
        ]);

        $jwt  = null;
        $csrf = null;
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            if (str_starts_with($cookie, 'jwt=')) {
                $jwt = explode(';', substr($cookie, 4))[0];
            }
            if (str_starts_with($cookie, 'csrf_token=')) {
                $csrf = explode(';', substr($cookie, 11))[0];
            }
        }

        return [$jwt, $csrf];
    }
}
