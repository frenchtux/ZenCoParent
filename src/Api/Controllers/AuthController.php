<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Auth\LoginCommand;
use ZenCoParent\Application\Auth\LoginHandler;
use ZenCoParent\Application\Auth\LoginResult;
use ZenCoParent\Application\Auth\LogoutHandler;
use ZenCoParent\Application\Auth\OAuthGoogleCommand;
use ZenCoParent\Application\Auth\OAuthGoogleHandler;
use ZenCoParent\Application\Auth\RefreshTokenCommand;
use ZenCoParent\Application\Auth\RefreshTokenHandler;
use ZenCoParent\Application\Auth\RegisterCommand;
use ZenCoParent\Application\Auth\RegisterHandler;
use ZenCoParent\Domain\Tenant\TenantRepositoryInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;
use ZenCoParent\Domain\User\UserTenantAccessRepositoryInterface;
use ZenCoParent\Infrastructure\Auth\GoogleOAuthService;

final class AuthController
{
    public function __construct(
        private LoginHandler                      $loginHandler,
        private LogoutHandler                     $logoutHandler,
        private RefreshTokenHandler               $refreshHandler,
        private OAuthGoogleHandler                $oauthHandler,
        private GoogleOAuthService                $googleOAuth,
        private RegisterHandler                   $registerHandler,
        private UserRepositoryInterface           $userRepo,
        private TenantRepositoryInterface         $tenantRepo,
        private UserTenantAccessRepositoryInterface $utaRepo,
        private \ZenCoParent\Infrastructure\Auth\JWTService $jwt,
    ) {}

    public function register(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        $familyName = trim((string) ($body['family_name'] ?? ''));
        $email      = trim((string) ($body['email']       ?? ''));
        $password   = (string) ($body['password']         ?? '');
        $firstName  = trim((string) ($body['first_name']  ?? ''));
        $lastName   = trim((string) ($body['last_name']   ?? ''));

        if ($familyName === '' || $email === '' || $password === '' || $firstName === '' || $lastName === '') {
            return ApiResponse::error($response, 'Champs requis : family_name, email, password, first_name, last_name', 400);
        }

        $result = $this->registerHandler->handle(
            new RegisterCommand($familyName, $email, $password, $firstName, $lastName)
        );

        return $this->applyAuthCookies($response, $result)->withStatus(201);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        $email      = trim((string) ($body['email']       ?? ''));
        $password   = (string) ($body['password']         ?? '');
        $tenantSlug = trim((string) ($body['tenant_slug'] ?? ''));

        if ($email === '' || $password === '' || $tenantSlug === '') {
            return ApiResponse::error($response, 'Fields email, password and tenant_slug are required', 400);
        }

        $result   = $this->loginHandler->handle(new LoginCommand($email, $password, $tenantSlug));
        $remember = filter_var($body['remember'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return $this->applyAuthCookies($response, $result, $remember)
            ->withStatus(200);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cookies      = $request->getCookieParams();
        $refreshToken = $cookies['refresh_token'] ?? null;

        if ($refreshToken !== null && $refreshToken !== '') {
            $this->logoutHandler->handle($refreshToken);
        }

        return $this->clearAuthCookies($response);
    }

    public function refresh(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cookies      = $request->getCookieParams();
        $refreshToken = $cookies['refresh_token'] ?? null;

        if ($refreshToken === null || $refreshToken === '') {
            return ApiResponse::error($response, 'Refresh token missing', 401);
        }

        $result = $this->refreshHandler->handle(new RefreshTokenCommand($refreshToken));

        return $this->applyAuthCookies($response, $result, self::rememberFrom($cookies));
    }

    public function oauthRedirect(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantSlug = $args['tenantSlug'] ?? '';

        // Generate a random nonce for CSRF protection of the OAuth flow
        $nonce = bin2hex(random_bytes(16));
        $state = $nonce; // state carries only the nonce; tenantSlug is in the callback URL

        $authUrl = $this->googleOAuth->getAuthorizationUrl($state);

        $secure     = ($_ENV['APP_ENV'] ?? 'production') !== 'local' ? '; Secure' : '';
        $nonceCookie = "oauth_nonce={$nonce}; Path=/auth/oauth; HttpOnly; SameSite=Lax{$secure}; Max-Age=600";

        return $response
            ->withStatus(302)
            ->withHeader('Location', $authUrl)
            ->withAddedHeader('Set-Cookie', $nonceCookie);
    }

    public function oauthCallback(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantSlug  = $args['tenantSlug'] ?? '';
        $queryParams = $request->getQueryParams();
        $code        = $queryParams['code']  ?? null;
        $state       = $queryParams['state'] ?? null;

        if ($code === null || $state === null) {
            return ApiResponse::error($response, 'Missing code or state parameter', 400);
        }

        // Verify CSRF nonce from cookie
        $cookies = $request->getCookieParams();
        $nonce   = $cookies['oauth_nonce'] ?? null;

        if ($nonce === null || !hash_equals($nonce, $state)) {
            return ApiResponse::error($response, 'Invalid OAuth state', 403);
        }

        $result = $this->oauthHandler->handle(new OAuthGoogleCommand($code, $tenantSlug));

        // Clear nonce cookie + set auth cookies
        $clearNonce = 'oauth_nonce=; Path=/auth/oauth; HttpOnly; SameSite=Lax; Max-Age=0';

        return $this->applyAuthCookies($response, $result)
            ->withAddedHeader('Set-Cookie', $clearNonce);
    }

    // ─── Multi-tenant switch ─────────────────────────────────────────────────

    /** POST /auth/switch-tenant — issue a new JWT for a different tenant */
    public function switchTenant(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $body     = (array)  $request->getParsedBody();
        $tenantId = trim((string) ($body['tenant_id'] ?? ''));

        if ($tenantId === '') {
            return ApiResponse::error($response, 'tenant_id est requis.', 400);
        }

        // Verify the target tenant exists
        $tenant = $this->tenantRepo->findById($tenantId);
        if ($tenant === null) {
            return ApiResponse::error($response, 'Tenant introuvable.', 404);
        }

        // Verify the user has access to that tenant
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            return ApiResponse::error($response, 'User introuvable.', 404);
        }

        // Access is valid if: user's own tenant OR explicit grant in user_tenant_access
        $ownTenant   = $user->getTenantId() === $tenantId;
        $grantedAccess = $this->utaRepo->hasAccess($userId, $tenantId);

        if (!$ownTenant && !$grantedAccess) {
            return ApiResponse::error($response, 'Accès non autorisé à ce tenant.', 403);
        }

        // Determine role for this tenant
        $accessList = $this->utaRepo->findTenantsByUserId($userId);
        $role = $user->getRole()->value; // default = own role
        foreach ($accessList as $access) {
            if ($access['id'] === $tenantId) {
                $role = $access['role'];
                break;
            }
        }

        $persist   = self::rememberFrom($request->getCookieParams());
        $jwtExpiry = $persist ? (int) ($_ENV['JWT_EXPIRY'] ?? 3600) : null;

        $newAccessToken = $this->jwt->generateAccessToken($userId, $tenantId, $role);
        $csrfToken      = bin2hex(random_bytes(32));

        $jwtCookie  = self::cookie('jwt', $newAccessToken, '/', true, $jwtExpiry);
        $csrfCookie = self::cookie('csrf_token', $csrfToken, '/', false, $jwtExpiry);

        return ApiResponse::success($response, [
            'tenant_id' => $tenantId,
            'tenant'    => ['id' => $tenant->getId(), 'name' => $tenant->getName(), 'slug' => $tenant->getSlug()],
            'role'      => $role,
        ])
            ->withAddedHeader('Set-Cookie', $jwtCookie)
            ->withAddedHeader('Set-Cookie', $csrfCookie);
    }

    // ─── Cookie helpers ───────────────────────────────────────────────────────

    /**
     * $persist = "rester connecté": cookies survive a browser/app restart. Otherwise
     * they are session cookies and the session ends when the browser closes.
     */
    private function applyAuthCookies(ResponseInterface $response, LoginResult $result, bool $persist = true): ResponseInterface
    {
        $jwtExpiry = $persist ? (int) ($_ENV['JWT_EXPIRY']         ?? 3600)    : null;
        $rfExpiry  = $persist ? (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000) : null;

        // csrf_token is NOT HttpOnly — the client JS reads it and sends as X-CSRF-Token header
        $csrfToken = bin2hex(random_bytes(32));

        return ApiResponse::success($response, [
            'user'                   => $result->user->toArray(),
            'pending_medical_reports'=> $result->pendingMedicalReports,
        ])
            ->withAddedHeader('Set-Cookie', self::cookie('jwt', $result->accessToken, '/', true, $jwtExpiry))
            ->withAddedHeader('Set-Cookie', self::cookie('refresh_token', $result->refreshToken, '/auth/refresh', true, $rfExpiry))
            ->withAddedHeader('Set-Cookie', self::cookie('csrf_token', $csrfToken, '/', false, $jwtExpiry))
            // Lets /auth/refresh and /auth/switch-tenant re-issue cookies with the same lifetime.
            ->withAddedHeader('Set-Cookie', self::cookie('remember', $persist ? '1' : '0', '/auth', true, $rfExpiry));
    }

    private function clearAuthCookies(ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success($response, null)
            ->withAddedHeader('Set-Cookie', self::cookie('jwt', '', '/', true, 0))
            ->withAddedHeader('Set-Cookie', self::cookie('refresh_token', '', '/auth/refresh', true, 0))
            ->withAddedHeader('Set-Cookie', self::cookie('csrf_token', '', '/', false, 0))
            ->withAddedHeader('Set-Cookie', self::cookie('remember', '', '/auth', true, 0));
    }

    /** Sessions opened before the "remember" cookie existed keep their persistent cookies. */
    private static function rememberFrom(array $cookies): bool
    {
        return ($cookies['remember'] ?? '1') !== '0';
    }

    /** $maxAge null = session cookie, deleted when the browser closes. */
    private static function cookie(string $name, string $value, string $path, bool $httpOnly, ?int $maxAge): string
    {
        $cookie = "{$name}={$value}; Path={$path}; SameSite=Strict";
        if ($httpOnly) {
            $cookie .= '; HttpOnly';
        }
        if (($_ENV['APP_ENV'] ?? 'production') !== 'local') {
            $cookie .= '; Secure';
        }
        if ($maxAge !== null) {
            $cookie .= "; Max-Age={$maxAge}";
        }
        return $cookie;
    }
}
