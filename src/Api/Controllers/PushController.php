<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Notification\PushNotificationService;
use ZenCoParent\Domain\Notification\PushSenderInterface;
use ZenCoParent\Domain\Notification\PushSubscriptionRepositoryInterface;

final class PushController
{
    /**
     * The server POSTs to whatever endpoint a user registers: only accept the
     * browsers' push services, otherwise this would be an SSRF vector.
     */
    private const ALLOWED_HOST_SUFFIXES = [
        'fcm.googleapis.com',
        'push.services.mozilla.com',
        'push.apple.com',
        'notify.windows.com',
    ];

    public function __construct(
        private readonly PushSenderInterface                 $sender,
        private readonly PushSubscriptionRepositoryInterface $subscriptions,
        private readonly PushNotificationService             $notifications,
    ) {}

    public function publicKey(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success($response, ['public_key' => $this->sender->publicKey()]);
    }

    public function subscribe(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $body     = (array) $request->getParsedBody();
        $keys     = is_array($body['keys'] ?? null) ? $body['keys'] : [];
        $endpoint = trim((string) ($body['endpoint'] ?? ''));
        $p256dh   = trim((string) ($keys['p256dh'] ?? ''));
        $auth     = trim((string) ($keys['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return ApiResponse::error($response, 'Champs requis : endpoint, keys.p256dh, keys.auth', 400);
        }
        if (!self::isAllowedEndpoint($endpoint)) {
            return ApiResponse::error($response, 'Service de notification non reconnu.', 422);
        }

        $userAgent = substr($request->getHeaderLine('User-Agent'), 0, 255) ?: null;
        $this->subscriptions->save($userId, $endpoint, $p256dh, $auth, $userAgent);

        return ApiResponse::success($response, null, 201);
    }

    public function unsubscribe(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId   = (string) $request->getAttribute('userId');
        $endpoint = trim((string) (((array) $request->getParsedBody())['endpoint'] ?? ''));

        if ($endpoint === '') {
            return ApiResponse::error($response, 'Champ requis : endpoint', 400);
        }
        $this->subscriptions->delete($userId, $endpoint);

        return ApiResponse::success($response, null);
    }

    public function test(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->notifications->test((string) $request->getAttribute('userId'));

        return ApiResponse::success($response, null);
    }

    private static function isAllowedEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }
        return false;
    }
}
