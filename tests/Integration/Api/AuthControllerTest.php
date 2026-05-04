<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

final class AuthControllerTest extends IntegrationTestCase
{
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->createTenant('Test Family', 'test-family');
        $this->createUser($this->tenantId, 'alice@example.com', 'Secret123!');
    }

    public function test_login_returns_200_with_user_data_on_valid_credentials(): void
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'alice@example.com',
            'password'    => 'Secret123!',
            'tenant_slug' => 'test-family',
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('alice@example.com', $body['data']['user']['email']);

        // jwt and refresh_token cookies must be set
        $setCookies = $response->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('jwt=', $setCookies);
        $this->assertStringContainsString('HttpOnly', $setCookies);
    }

    public function test_login_returns_401_on_wrong_password(): void
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'alice@example.com',
            'password'    => 'WrongPassword!',
            'tenant_slug' => 'test-family',
        ]);

        $this->assertSame(401, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertFalse($body['success']);
    }

    public function test_login_returns_400_when_fields_missing(): void
    {
        $response = $this->makeRequest('POST', '/auth/login', ['email' => 'alice@example.com']);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_login_returns_404_on_unknown_tenant(): void
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'alice@example.com',
            'password'    => 'Secret123!',
            'tenant_slug' => 'unknown-tenant',
        ]);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_logout_clears_cookies(): void
    {
        // Login first to get a valid JWT
        $loginResponse = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'alice@example.com',
            'password'    => 'Secret123!',
            'tenant_slug' => 'test-family',
        ]);

        $jwtToken = $this->extractCookieValue($loginResponse->getHeader('Set-Cookie'), 'jwt');
        $this->assertNotEmpty($jwtToken);

        $response = $this->makeRequest(
            'POST',
            '/auth/logout',
            cookies: ['jwt' => $jwtToken],
        );

        $this->assertSame(200, $response->getStatusCode());

        // Cookies must be cleared (Max-Age=0)
        $setCookieHeader = implode(', ', $response->getHeader('Set-Cookie'));
        $this->assertStringContainsString('Max-Age=0', $setCookieHeader);
    }

    public function test_logout_returns_401_without_jwt(): void
    {
        $response = $this->makeRequest('POST', '/auth/logout');
        $this->assertSame(401, $response->getStatusCode());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function extractCookieValue(array $setCookieHeaders, string $name): ?string
    {
        foreach ($setCookieHeaders as $header) {
            if (str_starts_with($header, "{$name}=")) {
                return explode(';', substr($header, strlen($name) + 1))[0];
            }
        }
        return null;
    }
}
