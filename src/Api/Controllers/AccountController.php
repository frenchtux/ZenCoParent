<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\User\DeleteAccountHandler;
use ZenCoParent\Application\User\GdprExportHandler;

final class AccountController
{
    public function __construct(
        private readonly GdprExportHandler    $exportHandler,
        private readonly DeleteAccountHandler $deleteHandler,
    ) {}

    /** GET /account/export — RGPD data export */
    public function export(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args,
    ): ResponseInterface {
        $userId   = (string) $request->getAttribute('userId');
        $tenantId = (string) $request->getAttribute('tenantId');

        $data = $this->exportHandler->handle($userId, $tenantId);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($json);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="zencoparent-export.json"');
    }

    /** DELETE /account — delete account + invalidate sessions */
    public function delete(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args,
    ): ResponseInterface {
        $userId   = (string) $request->getAttribute('userId');
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();
        $password = trim((string) ($body['password'] ?? ''));

        if ($password === '') {
            return ApiResponse::error($response, 'Le mot de passe est requis pour supprimer le compte.', 400);
        }

        try {
            $this->deleteHandler->handle($userId, $tenantId, $password);
        } catch (\ZenCoParent\Domain\Shared\Exception\NotFoundException $e) {
            return ApiResponse::error($response, $e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, $e->getMessage(), 400);
        }

        return $response->withStatus(204);
    }
}
