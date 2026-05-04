<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\E2E;

use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

/**
 * End-to-end auth flow: register → login → access protected resource → logout.
 */
final class AuthFlowTest extends IntegrationTestCase
{
    public function test_full_auth_flow(): void
    {
        // 1. Setup tenant
        $tenantId = $this->createTenant('E2E Family', 'e2e-family');

        // 2. Create initial admin via fixture (no HTTP yet — bootstrapping)
        $adminId = $this->createUser($tenantId, 'admin@e2e.com', 'Admin123!', 'admin');

        // 3. Admin logs in
        $loginResponse = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'admin@e2e.com',
            'password'    => 'Admin123!',
            'tenant_slug' => 'e2e-family',
        ]);

        $this->assertSame(200, $loginResponse->getStatusCode());

        $loginBody  = $this->decodeJson($loginResponse);
        $this->assertSame('admin@e2e.com', $loginBody['data']['user']['email']);
        $this->assertSame('admin', $loginBody['data']['user']['role']);

        // Extract JWT from Set-Cookie header
        $jwtToken = $this->extractCookieValue($loginResponse->getHeader('Set-Cookie'), 'jwt');
        $this->assertNotEmpty($jwtToken, 'JWT cookie must be set after login');

        // 4. Access protected resource with JWT
        $usersResponse = $this->makeRequest('GET', '/users', cookies: ['jwt' => $jwtToken]);
        $this->assertSame(200, $usersResponse->getStatusCode());
        $usersBody = $this->decodeJson($usersResponse);
        $this->assertCount(1, $usersBody['data']);

        // 5. Admin creates a new parent user
        $createResponse = $this->makeRequest(
            'POST',
            '/users',
            body: [
                'email'      => 'parent@e2e.com',
                'password'   => 'Parent123!',
                'first_name' => 'Marie',
                'last_name'  => 'Dupont',
                'role'       => 'parent',
            ],
            cookies: ['jwt' => $jwtToken],
        );

        $this->assertSame(201, $createResponse->getStatusCode());
        $createBody = $this->decodeJson($createResponse);
        $this->assertSame('parent@e2e.com', $createBody['data']['email']);

        // 6. New parent can log in
        $parentLogin = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'parent@e2e.com',
            'password'    => 'Parent123!',
            'tenant_slug' => 'e2e-family',
        ]);
        $this->assertSame(200, $parentLogin->getStatusCode());
        $parentJwt = $this->extractCookieValue($parentLogin->getHeader('Set-Cookie'), 'jwt');

        // 7. Parent creates a child
        $childResponse = $this->makeRequest(
            'POST',
            '/children',
            body: [
                'first_name' => 'Emma',
                'last_name'  => 'Dupont',
                'birthdate'  => '2016-03-20',
            ],
            cookies: ['jwt' => $parentJwt],
        );

        $this->assertSame(201, $childResponse->getStatusCode());
        $childBody = $this->decodeJson($childResponse);
        $this->assertSame('Emma', $childBody['data']['first_name']);

        // 8. Admin logs out
        $logoutResponse = $this->makeRequest('POST', '/auth/logout', cookies: ['jwt' => $jwtToken]);
        $this->assertSame(200, $logoutResponse->getStatusCode());

        // 9. JWT is no longer valid (cookies cleared)
        $setCookies = implode(', ', $logoutResponse->getHeader('Set-Cookie'));
        $this->assertStringContainsString('Max-Age=0', $setCookies);

        // 10. Accessing protected resource after logout returns 401
        // (JWT cookie was only in our local $jwtToken var — the real browser would send
        //  the cleared cookie. We simulate by sending an empty jwt cookie.)
        $postLogoutResponse = $this->makeRequest('GET', '/users', cookies: ['jwt' => '']);
        $this->assertSame(401, $postLogoutResponse->getStatusCode());
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
