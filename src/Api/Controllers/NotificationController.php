<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Domain\Messaging\MessageRepositoryInterface;

final class NotificationController
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepo,
    ) {}

    /** GET /notifications/summary */
    public function summary(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args,
    ): ResponseInterface {
        $userId   = (string) $request->getAttribute('userId');
        $tenantId = (string) $request->getAttribute('tenantId');

        $unread = $this->messageRepo->countAllUnreadForUser($userId, $tenantId);

        return ApiResponse::success($response, ['unread_messages' => $unread]);
    }
}
