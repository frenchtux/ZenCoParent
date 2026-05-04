<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Infrastructure\Auth\JWTService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private JWTService $jwt) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $token   = $cookies['jwt'] ?? null;

        if ($token === null || $token === '') {
            $response = (new ResponseFactory())->createResponse();
            return ApiResponse::error($response, 'Authentication required', 401);
        }

        try {
            $payload = $this->jwt->validateAccessToken($token);
        } catch (\Throwable) {
            $response = (new ResponseFactory())->createResponse();
            return ApiResponse::error($response, 'Invalid or expired token', 401);
        }

        $request = $request
            ->withAttribute('userId',   $payload['sub'])
            ->withAttribute('tenantId', $payload['tenant_id'])
            ->withAttribute('role',     $payload['role']);

        return $handler->handle($request);
    }
}
