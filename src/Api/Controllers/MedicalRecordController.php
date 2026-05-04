<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\MedicalRecord\CreateMedicalRecordCommand;
use ZenCoParent\Application\MedicalRecord\CreateMedicalRecordHandler;
use ZenCoParent\Application\MedicalRecord\GetChildMedicalHistoryHandler;

final class MedicalRecordController
{
    public function __construct(
        private CreateMedicalRecordHandler      $createHandler,
        private GetChildMedicalHistoryHandler   $historyHandler,
    ) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $userId   = (string) $request->getAttribute('userId');
        $body     = (array) $request->getParsedBody();

        foreach (['child_id', 'report'] as $field) {
            if (empty($body[$field])) {
                return ApiResponse::error($response, "Field '{$field}' is required", 400);
            }
        }

        $command = new CreateMedicalRecordCommand(
            tenantId:    $tenantId,
            childId:     (string) $body['child_id'],
            report:      (string) $body['report'],
            createdBy:   $userId,
            eventId:     isset($body['event_id'])     ? (string) $body['event_id']     : null,
            practitioner:isset($body['practitioner']) ? (string) $body['practitioner'] : null,
            recordedAt:  isset($body['recorded_at'])  ? (string) $body['recorded_at']  : null,
        );

        $dto = $this->createHandler->handle($command);

        return ApiResponse::success($response, $dto->toArray(), 201);
    }

    public function childHistory(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $childId  = (string) $args['id'];

        $records = $this->historyHandler->handle($childId, $tenantId);

        return ApiResponse::success($response, array_map(fn($r) => $r->toArray(), $records));
    }
}
