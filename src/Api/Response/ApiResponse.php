<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Response;

use Psr\Http\Message\ResponseInterface;

final class ApiResponse
{
    public static function success(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $payload = json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response->getBody()->write($payload);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    public static function error(
        ResponseInterface $response,
        string $error,
        int $status = 400,
        ?array $errors = null,
    ): ResponseInterface {
        $body = ['success' => false, 'error' => $error];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response->getBody()->write($payload);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
