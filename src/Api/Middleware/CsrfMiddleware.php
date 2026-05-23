<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use ZenCoParent\Api\Response\ApiResponse;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const EXEMPT_PREFIXES = [
        '/auth/login',
        '/auth/oauth/google',
        '/auth/refresh',
        '/auth/register',
    ];

    // Full-path exemptions (exact match for public invitation endpoints)
    private const EXEMPT_PATTERNS = [
        '#^/invitations/[^/]+$#',          // GET/POST /invitations/{token}
        '#^/invitations/[^/]+/accept$#',   // POST /invitations/{token}/accept
    ];

    private const STATE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Allow bypassing CSRF in test environment
        if (($_ENV['APP_ENV'] ?? 'production') === 'testing') {
            return $handler->handle($request);
        }

        $method = strtoupper($request->getMethod());
        $path   = $request->getUri()->getPath();

        $isStateful = in_array($method, self::STATE_METHODS, true);
        $isExempt   = $this->isExempt($path);

        if ($isStateful && !$isExempt) {
            $cookies     = $request->getCookieParams();
            $cookieToken = $cookies['csrf_token'] ?? null;
            $headerToken = $request->getHeaderLine('X-CSRF-Token');

            if ($cookieToken === null || $cookieToken === '' || $headerToken !== $cookieToken) {
                $response = (new ResponseFactory())->createResponse();
                return ApiResponse::error($response, 'Invalid CSRF token', 403);
            }
        }

        $response = $handler->handle($request);

        // Set csrf_token cookie on the response if not already present in the request
        $cookies = $request->getCookieParams();
        if (!isset($cookies['csrf_token']) || $cookies['csrf_token'] === '') {
            $token      = bin2hex(random_bytes(32));
            $secure     = ($_ENV['APP_ENV'] ?? 'production') !== 'local' ? '; Secure' : '';
            $cookieLine = "csrf_token={$token}; Path=/; SameSite=Strict{$secure}; Max-Age=86400";
            $response   = $response->withAddedHeader('Set-Cookie', $cookieLine);
        }

        return $response;
    }

    private function isExempt(string $path): bool
    {
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        foreach (self::EXEMPT_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
