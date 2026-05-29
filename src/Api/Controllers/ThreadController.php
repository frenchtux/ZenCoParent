<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Messaging\CreateThreadCommand;
use ZenCoParent\Application\Messaging\CreateThreadHandler;
use ZenCoParent\Application\Messaging\GetThreadHandler;
use ZenCoParent\Application\Messaging\ListThreadsHandler;
use ZenCoParent\Application\Messaging\ListMessagesHandler;
use ZenCoParent\Application\Messaging\MarkMessageReadHandler;
use ZenCoParent\Application\Messaging\SendMessageCommand;
use ZenCoParent\Application\Messaging\SendMessageHandler;

final class ThreadController
{
    public function __construct(
        private ListThreadsHandler    $listThreadsHandler,
        private CreateThreadHandler   $createThreadHandler,
        private GetThreadHandler      $getThreadHandler,
        private ListMessagesHandler   $listMessagesHandler,
        private SendMessageHandler    $sendMessageHandler,
        private MarkMessageReadHandler $markReadHandler,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $tenantId = (string) $request->getAttribute('tenantId');

        $threads = $this->listThreadsHandler->handle($userId, $tenantId);

        return ApiResponse::success($response, array_map(fn($t) => $t->toArray(), $threads));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $tenantId = (string) $request->getAttribute('tenantId');
        $body     = (array) $request->getParsedBody();

        if (empty($body['type'])) {
            return ApiResponse::error($response, "Field 'type' is required", 400);
        }

        $command = new CreateThreadCommand(
            tenantId:       $tenantId,
            type:           (string) $body['type'],
            createdBy:      $userId,
            participantIds: isset($body['participant_ids']) && is_array($body['participant_ids'])
                                ? array_map('strval', $body['participant_ids'])
                                : [],
            subject:        isset($body['subject']) && $body['subject'] !== ''
                                ? trim((string) $body['subject'])
                                : null,
        );

        $threadDto = $this->createThreadHandler->handle($command);

        return ApiResponse::success($response, $threadDto->toArray(), 201);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $threadId = (string) $args['id'];

        $threadDto = $this->getThreadHandler->handle($threadId, $userId);

        return ApiResponse::success($response, $threadDto->toArray());
    }

    public function messages(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $threadId = (string) $args['id'];
        $params   = $request->getQueryParams();

        $since = isset($params['since']) && $params['since'] !== '' ? (string) $params['since'] : null;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 50;

        $messages = $this->listMessagesHandler->handle($threadId, $userId, $since, $limit);

        return ApiResponse::success($response, array_map(fn($m) => $m->toArray(), $messages));
    }

    public function sendMessage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $tenantId = (string) $request->getAttribute('tenantId');
        $threadId = (string) $args['id'];
        $body     = (array) $request->getParsedBody();

        if (empty($body['content'])) {
            return ApiResponse::error($response, "Field 'content' is required", 400);
        }

        $command = new SendMessageCommand(
            threadId: $threadId,
            tenantId: $tenantId,
            senderId: $userId,
            content:  (string) $body['content'],
        );

        $messageDto = $this->sendMessageHandler->handle($command);

        return ApiResponse::success($response, $messageDto->toArray(), 201);
    }

    public function markRead(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId    = (string) $request->getAttribute('userId');
        $threadId  = (string) $args['id'];
        $messageId = (string) $args['msgId'];

        $this->markReadHandler->handle($messageId, $threadId, $userId);

        return ApiResponse::success($response, null);
    }
}
