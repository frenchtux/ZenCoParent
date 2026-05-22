<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\User\ChangePasswordCommand;
use ZenCoParent\Application\User\ChangePasswordHandler;
use ZenCoParent\Application\User\CreateUserCommand;
use ZenCoParent\Application\User\CreateUserHandler;
use ZenCoParent\Application\User\GetUserHandler;
use ZenCoParent\Application\User\ListUsersHandler;
use ZenCoParent\Application\User\UpdateUserCommand;
use ZenCoParent\Application\User\UpdateUserHandler;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;

final class UserController
{
    public function __construct(
        private ListUsersHandler     $listHandler,
        private CreateUserHandler    $createHandler,
        private GetUserHandler       $getHandler,
        private UpdateUserHandler    $updateHandler,
        private ChangePasswordHandler $changePasswordHandler,
    ) {}

    /** GET /users */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $users    = $this->listHandler->handle($tenantId);

        return ApiResponse::success($response, array_map(fn($u) => $u->toArray(), $users));
    }

    /** GET /users/me */
    public function me(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $userId   = (string) $request->getAttribute('userId');

        try {
            $dto = $this->getHandler->handle($userId, $tenantId);
            return ApiResponse::success($response, $dto->toArray());
        } catch (NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        }
    }

    /** GET /users/{id} */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $id       = (string) $args['id'];

        try {
            $dto = $this->getHandler->handle($id, $tenantId);
            return ApiResponse::success($response, $dto->toArray());
        } catch (NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        }
    }

    /** POST /users  (admin only) */
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();

        $required = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return ApiResponse::error($response, "Le champ '{$field}' est requis.", 400);
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

        try {
            $dto = $this->createHandler->handle($command);
            return ApiResponse::success($response, $dto->toArray(), 201);
        } catch (\Exception $e) {
            return ApiResponse::error($response, $e->getMessage(), 409);
        }
    }

    /** PUT /users/{id} */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId      = (string) $request->getAttribute('tenantId');
        $currentUserId = (string) $request->getAttribute('userId');
        $currentRole   = (string) $request->getAttribute('role');
        $id            = (string) $args['id'];

        // Users can only edit their own profile; admins can edit anyone
        if ($currentRole !== 'admin' && $id !== $currentUserId) {
            return ApiResponse::error($response, "Action non autorisée.", 403);
        }

        $body = (array) $request->getParsedBody();

        if (empty($body['first_name']) || empty($body['last_name'])) {
            return ApiResponse::error($response, "Prénom et nom sont requis.", 400);
        }

        // Only admins can change role / active status
        $role     = ($currentRole === 'admin' && isset($body['role']))      ? (string) $body['role']      : null;
        $isActive = ($currentRole === 'admin' && isset($body['is_active'])) ? (bool)   $body['is_active'] : null;

        $command = new UpdateUserCommand(
            id:        $id,
            tenantId:  $tenantId,
            firstName: trim((string) $body['first_name']),
            lastName:  trim((string) $body['last_name']),
            phone:     isset($body['phone'])   ? trim((string) $body['phone'])   : null,
            address:   isset($body['address']) ? trim((string) $body['address']) : null,
            role:      $role,
            isActive:  $isActive,
        );

        try {
            $dto = $this->updateHandler->handle($command);
            return ApiResponse::success($response, $dto->toArray());
        } catch (NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        }
    }

    /** PATCH /users/{id}/password */
    public function changePassword(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId      = (string) $request->getAttribute('tenantId');
        $currentUserId = (string) $request->getAttribute('userId');
        $currentRole   = (string) $request->getAttribute('role');
        $id            = (string) $args['id'];

        // Users can only change their own password
        if ($currentRole !== 'admin' && $id !== $currentUserId) {
            return ApiResponse::error($response, "Action non autorisée.", 403);
        }

        $body = (array) $request->getParsedBody();

        if (empty($body['new_password'])) {
            return ApiResponse::error($response, "Le nouveau mot de passe est requis.", 400);
        }

        $isAdminReset = $currentRole === 'admin' && $id !== $currentUserId;

        $command = new ChangePasswordCommand(
            id:              $id,
            tenantId:        $tenantId,
            currentPassword: isset($body['current_password']) ? (string) $body['current_password'] : null,
            newPassword:     (string) $body['new_password'],
            isAdminReset:    $isAdminReset,
        );

        try {
            $this->changePasswordHandler->handle($command);
            return ApiResponse::success($response, ['message' => 'Mot de passe mis à jour.']);
        } catch (NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        } catch (UnauthorizedException $e) {
            return ApiResponse::error($response, $e->getMessage(), 401);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, $e->getMessage(), 400);
        }
    }
}
