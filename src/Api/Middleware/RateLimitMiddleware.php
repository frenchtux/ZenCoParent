<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Infrastructure\Cache\RedisRateLimiter;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private RedisRateLimiter $limiter) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $serverParams = $request->getServerParams();

        $ip = $serverParams['HTTP_X_FORWARDED_FOR']
            ?? $serverParams['REMOTE_ADDR']
            ?? '0.0.0.0';

        // Use only first IP if comma-separated (behind proxy)
        $ip = trim(explode(',', $ip)[0]);

        if (!$this->limiter->isAllowed($ip)) {
            $response = (new ResponseFactory())->createResponse();
            return ApiResponse::error($response, 'Too many requests', 429);
        }

        $remaining = $this->limiter->getRemainingRequests($ip);

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }
}
