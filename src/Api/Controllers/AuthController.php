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
use ZenCoParent\Infrastructure\Auth\GoogleOAuthService;

final class AuthController
{
    public function __construct(
        private LoginHandler        $loginHandler,
        private LogoutHandler       $logoutHandler,
        private RefreshTokenHandler $refreshHandler,
        private OAuthGoogleHandler  $oauthHandler,
        private GoogleOAuthService  $googleOAuth,
        private RegisterHandler     $registerHandler,
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

        $result = $this->loginHandler->handle(new LoginCommand($email, $password, $tenantSlug));

        return $this->applyAuthCookies($response, $result)
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

        return $this->applyAuthCookies($response, $result);
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

    // ─── Cookie helpers ───────────────────────────────────────────────────────

    private function applyAuthCookies(ResponseInterface $response, LoginResult $result): ResponseInterface
    {
        $secure      = ($_ENV['APP_ENV'] ?? 'production') !== 'local' ? '; Secure' : '';
        $jwtExpiry   = (int) ($_ENV['JWT_EXPIRY']         ?? 3600);
        $rfExpiry    = (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000);

        $jwtCookie = "jwt={$result->accessToken}; Path=/; HttpOnly; SameSite=Strict{$secure}; Max-Age={$jwtExpiry}";
        $rfCookie  = "refresh_token={$result->refreshToken}; Path=/auth/refresh; HttpOnly; SameSite=Strict{$secure}; Max-Age={$rfExpiry}";

        // csrf_token is NOT HttpOnly — the client JS reads it and sends as X-CSRF-Token header
        $csrfToken  = bin2hex(random_bytes(32));
        $csrfCookie = "csrf_token={$csrfToken}; Path=/; SameSite=Strict{$secure}; Max-Age={$jwtExpiry}";

        return ApiResponse::success($response, ['user' => $result->user->toArray()])
            ->withAddedHeader('Set-Cookie', $jwtCookie)
            ->withAddedHeader('Set-Cookie', $rfCookie)
            ->withAddedHeader('Set-Cookie', $csrfCookie);
    }

    private function clearAuthCookies(ResponseInterface $response): ResponseInterface
    {
        $jwtClear  = 'jwt=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0';
        $rfClear   = 'refresh_token=; Path=/auth/refresh; HttpOnly; SameSite=Strict; Max-Age=0';
        $csrfClear = 'csrf_token=; Path=/; SameSite=Strict; Max-Age=0';

        return ApiResponse::success($response, null)
            ->withAddedHeader('Set-Cookie', $jwtClear)
            ->withAddedHeader('Set-Cookie', $rfClear)
            ->withAddedHeader('Set-Cookie', $csrfClear);
    }
}
