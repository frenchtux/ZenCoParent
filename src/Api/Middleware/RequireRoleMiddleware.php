<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use ZenCoParent\Api\Response\ApiResponse;

final class RequireRoleMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedRoles */
    public function __construct(private readonly array $allowedRoles) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $role = $request->getAttribute('role');

        if (!in_array($role, $this->allowedRoles, true)) {
            $response = (new ResponseFactory())->createResponse();
            return ApiResponse::error($response, 'Forbidden', 403);
        }

        return $handler->handle($request);
    }
}
