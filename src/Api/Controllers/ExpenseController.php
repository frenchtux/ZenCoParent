<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Expense\CreateExpenseCommand;
use ZenCoParent\Application\Expense\CreateExpenseHandler;
use ZenCoParent\Application\Expense\DeleteExpenseHandler;
use ZenCoParent\Application\Expense\ListExpensesHandler;
use ZenCoParent\Application\Expense\UpdateExpenseCommand;
use ZenCoParent\Application\Expense\UpdateExpenseHandler;

final class ExpenseController
{
    public function __construct(
        private ListExpensesHandler  $listHandler,
        private CreateExpenseHandler $createHandler,
        private UpdateExpenseHandler $updateHandler,
        private DeleteExpenseHandler $deleteHandler,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $params   = $request->getQueryParams();

        $expenses = $this->listHandler->handle(
            tenantId: $tenantId,
            paidBy:   $params['paid_by']  ?? null,
            category: $params['category'] ?? null,
            from:     $params['from']     ?? null,
            to:       $params['to']       ?? null,
        );

        return ApiResponse::success($response, array_map(fn($e) => $e->toArray(), $expenses));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $userId   = (string) $request->getAttribute('userId');
        $body     = (array) $request->getParsedBody();

        foreach (['amount', 'description', 'date'] as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                return ApiResponse::error($response, "Field '{$field}' is required", 400);
            }
        }

        $command = new CreateExpenseCommand(
            tenantId:    $tenantId,
            paidBy:      $userId,
            amount:      (float) $body['amount'],
            description: (string) $body['description'],
            category:    isset($body['category']) && $body['category'] !== '' ? (string) $body['category'] : null,
            splitRatio:  isset($body['split_ratio']) && is_array($body['split_ratio'])
                             ? $body['split_ratio']
                             : [],
            date:        (string) $body['date'],
        );

        $dto = $this->createHandler->handle($command);

        return ApiResponse::success($response, $dto->toArray(), 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();

        foreach (['amount', 'description', 'date'] as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                return ApiResponse::error($response, "Field '{$field}' is required", 400);
            }
        }

        $command = new UpdateExpenseCommand(
            id:          (string) $args['id'],
            tenantId:    $tenantId,
            amount:      (float) $body['amount'],
            description: (string) $body['description'],
            category:    isset($body['category']) && $body['category'] !== '' ? (string) $body['category'] : null,
            splitRatio:  isset($body['split_ratio']) && is_array($body['split_ratio'])
                             ? $body['split_ratio']
                             : [],
            date:        (string) $body['date'],
        );

        $dto = $this->updateHandler->handle($command);

        return ApiResponse::success($response, $dto->toArray());
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $this->deleteHandler->handle((string) $args['id'], $tenantId);

        return ApiResponse::success($response, null, 204);
    }
}
