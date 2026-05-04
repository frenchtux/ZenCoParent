<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Event\CreateEventCommand;
use ZenCoParent\Application\Event\CreateEventHandler;
use ZenCoParent\Application\Event\DeleteEventHandler;
use ZenCoParent\Application\Event\GetEventHandler;
use ZenCoParent\Application\Event\ListEventsHandler;
use ZenCoParent\Application\Event\UpdateEventCommand;
use ZenCoParent\Application\Event\UpdateEventHandler;

final class EventController
{
    public function __construct(
        private ListEventsHandler  $listHandler,
        private CreateEventHandler $createHandler,
        private GetEventHandler    $getHandler,
        private UpdateEventHandler $updateHandler,
        private DeleteEventHandler $deleteHandler,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $params   = $request->getQueryParams();

        $events = $this->listHandler->handle(
            tenantId: $tenantId,
            childId:  $params['child_id'] ?? null,
            type:     $params['type']     ?? null,
            from:     $params['from']     ?? null,
            to:       $params['to']       ?? null,
        );

        return ApiResponse::success($response, array_map(fn($e) => $e->toArray(), $events));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $userId   = (string) $request->getAttribute('userId');
        $body     = (array) $request->getParsedBody();

        foreach (['title', 'type', 'start_at', 'end_at'] as $field) {
            if (empty($body[$field])) {
                return ApiResponse::error($response, "Field '{$field}' is required", 400);
            }
        }

        $command = new CreateEventCommand(
            tenantId:    $tenantId,
            title:       trim((string) $body['title']),
            type:        (string) $body['type'],
            startAt:     (string) $body['start_at'],
            endAt:       (string) $body['end_at'],
            allDay:      (bool) ($body['all_day'] ?? false),
            createdBy:   $userId,
            childId:     isset($body['child_id'])    ? (string) $body['child_id']    : null,
            description: isset($body['description']) ? (string) $body['description'] : null,
            report:      isset($body['report'])      ? (string) $body['report']      : null,
            practitioner:isset($body['practitioner'])? (string) $body['practitioner']: null,
            recordedAt:  isset($body['recorded_at']) ? (string) $body['recorded_at'] : null,
        );

        $eventDto = $this->createHandler->handle($command);

        return ApiResponse::success($response, $eventDto->toArray(), 201);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $eventDto = $this->getHandler->handle((string) $args['id'], $tenantId);

        return ApiResponse::success($response, $eventDto->toArray());
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();

        foreach (['title', 'type', 'start_at', 'end_at'] as $field) {
            if (empty($body[$field])) {
                return ApiResponse::error($response, "Field '{$field}' is required", 400);
            }
        }

        $command = new UpdateEventCommand(
            id:          (string) $args['id'],
            tenantId:    $tenantId,
            title:       trim((string) $body['title']),
            type:        (string) $body['type'],
            startAt:     (string) $body['start_at'],
            endAt:       (string) $body['end_at'],
            allDay:      (bool) ($body['all_day'] ?? false),
            childId:     isset($body['child_id'])    ? (string) $body['child_id']    : null,
            description: isset($body['description']) ? (string) $body['description'] : null,
        );

        $eventDto = $this->updateHandler->handle($command);

        return ApiResponse::success($response, $eventDto->toArray());
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $this->deleteHandler->handle((string) $args['id'], $tenantId);

        return ApiResponse::success($response, null, 204);
    }
}
