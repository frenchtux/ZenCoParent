<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Child\CreateChildCommand;
use ZenCoParent\Application\Child\CreateChildHandler;
use ZenCoParent\Application\Child\ListChildrenHandler;
use ZenCoParent\Application\Child\UpdateChildCommand;
use ZenCoParent\Application\Child\UpdateChildHandler;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class ChildController
{
    public function __construct(
        private ListChildrenHandler $listHandler,
        private CreateChildHandler  $createHandler,
        private UpdateChildHandler  $updateHandler,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $children = $this->listHandler->handle($tenantId);

        return ApiResponse::success($response, array_map(fn($c) => $c->toArray(), $children));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $userId   = (string) $request->getAttribute('userId');
        $body     = (array) $request->getParsedBody();

        if (empty($body['first_name'])) {
            return ApiResponse::error($response, "Field 'first_name' is required", 400);
        }
        if (empty($body['last_name'])) {
            return ApiResponse::error($response, "Field 'last_name' is required", 400);
        }

        $command = new CreateChildCommand(
            tenantId:  $tenantId,
            firstName: trim((string) $body['first_name']),
            lastName:  trim((string) $body['last_name']),
            birthdate: isset($body['birthdate']) ? trim((string) $body['birthdate']) : null,
            createdBy: $userId,
        );

        $childDto = $this->createHandler->handle($command);

        return ApiResponse::success($response, $childDto->toArray(), 201);
    }

    /** PUT /children/{id} */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $id       = (string) $args['id'];
        $body     = (array) $request->getParsedBody();

        if (empty($body['first_name'])) {
            return ApiResponse::error($response, "Le champ 'first_name' est requis.", 400);
        }

        $command = new UpdateChildCommand(
            id:        $id,
            tenantId:  $tenantId,
            firstName: trim((string) $body['first_name']),
            lastName:  trim((string) ($body['last_name'] ?? '')),
            birthdate: isset($body['birthdate']) && $body['birthdate'] !== '' ? trim((string) $body['birthdate']) : null,
        );

        try {
            $dto = $this->updateHandler->handle($command);
            return ApiResponse::success($response, $dto->toArray());
        } catch (NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        }
    }
}
