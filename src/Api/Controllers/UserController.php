<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\User\CreateUserCommand;
use ZenCoParent\Application\User\CreateUserHandler;
use ZenCoParent\Application\User\ListUsersHandler;

final class UserController
{
    public function __construct(
        private ListUsersHandler  $listHandler,
        private CreateUserHandler $createHandler,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $users    = $this->listHandler->handle($tenantId);

        return ApiResponse::success($response, array_map(fn($u) => $u->toArray(), $users));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();

        $required = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return ApiResponse::error($response, "Field '{$field}' is required", 400);
            }
        }

        $command = new CreateUserCommand(
            tenantId:  $tenantId,
            email:     trim((string) $body['email']),
            password:  (string) $body['password'],
            firstName: trim((string) $body['first_name']),
            lastName:  trim((string) $body['last_name']),
            role:      (string) ($body['role']    ?? 'parent'),
            phone:     isset($body['phone'])   ? trim((string) $body['phone'])   : null,
            address:   isset($body['address']) ? trim((string) $body['address']) : null,
        );

        $userDto = $this->createHandler->handle($command);

        return ApiResponse::success($response, $userDto->toArray(), 201);
    }
}
