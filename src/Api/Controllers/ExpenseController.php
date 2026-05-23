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
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class ExpenseController
{
    public function __construct(
        private ListExpensesHandler   $listHandler,
        private CreateExpenseHandler  $createHandler,
        private UpdateExpenseHandler  $updateHandler,
        private DeleteExpenseHandler  $deleteHandler,
    ) {}

    /** GET /expenses */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $params   = $request->getQueryParams();

        $expenses = $this->listHandler->handle(
            tenantId: $tenantId,
            from:     $params['from'] ?? null,
            to:       $params['to']   ?? null,
        );

        return ApiResponse::success($response, array_map(fn($e) => $e->toArray(), $expenses));
    }

    /** POST /expenses */
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $userId   = (string) $request->getAttribute('userId');
        $body     = (array) $request->getParsedBody();

        if (empty($body['description'])) {
            return ApiResponse::error($response, "Le champ 'description' est requis.", 400);
        }
        if (empty($body['amount']) || (float) $body['amount'] <= 0) {
            return ApiResponse::error($response, "Le montant doit être supérieur à 0.", 400);
        }
        if (empty($body['date'])) {
            return ApiResponse::error($response, "Le champ 'date' est requis.", 400);
        }

        $command = new CreateExpenseCommand(
            tenantId:    $tenantId,
            paidBy:      isset($body['paid_by']) && $body['paid_by'] !== '' ? (string) $body['paid_by'] : $userId,
            amount:      (float) $body['amount'],
            description: trim((string) $body['description']),
            date:        (string) $body['date'],
            category:    isset($body['category']) && $body['category'] !== '' ? (string) $body['category'] : null,
        );

        try {
            $expense = $this->createHandler->handle($command);
            return ApiResponse::success($response, $expense->toArray(), 201);
        } catch (\Exception $e) {
            return ApiResponse::error($response, $e->getMessage(), 422);
        }
    }

    /** PUT /expenses/{id} */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $id       = (string) $args['id'];
        $body     = (array) $request->getParsedBody();

        if (empty($body['description'])) {
            return ApiResponse::error($response, "Le champ 'description' est requis.", 400);
        }
        if (empty($body['amount']) || (float) $body['amount'] <= 0) {
            return ApiResponse::error($response, "Le montant doit être supérieur à 0.", 400);
        }
        if (empty($body['date'])) {
            return ApiResponse::error($response, "Le champ 'date' est requis.", 400);
        }

        $command = new UpdateExpenseCommand(
            id:          $id,
            tenantId:    $tenantId,
            amount:      (float) $body['amount'],
            description: trim((string) $body['description']),
            date:        (string) $body['date'],
            category:    isset($body['category']) && $body['category'] !== '' ? (string) $body['category'] : null,
        );

        try {
            $expense = $this->updateHandler->handle($command);
            return ApiResponse::success($response, $expense->toArray());
        } catch (NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        }
    }

    /** DELETE /expenses/{id} */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $id       = (string) $args['id'];

        try {
            $this->deleteHandler->handle($id, $tenantId);
            return ApiResponse::success($response, null, 204);
        } catch (NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        }
    }
}
