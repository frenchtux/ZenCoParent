<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequireMasterKeyMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $masterKey) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth  = $request->getHeaderLine('Authorization');
        $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';

        if ($this->masterKey === '' || !hash_equals($this->masterKey, $token)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => 'unauthorized',
                'message' => 'Valid LICENSE_MASTER_KEY required as Bearer token.',
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
