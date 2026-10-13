<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Notification;

use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use ZenCoParent\Application\Settings\TenantSettingsService;
use ZenCoParent\Domain\Notification\PushSenderInterface;
use ZenCoParent\Domain\Notification\PushSubscriptionRepositoryInterface;

/**
 * Web Push with a VAPID key pair generated per installation, so every
 * self-hosted server sends its own notifications without any third-party account.
 */
final class WebPushGateway implements PushSenderInterface
{
    private const SETTING_KEY = 'vapid_keys';

    /** @var array<array{subscription: array, payload: string}> */
    private array $queue = [];
    private bool $flushScheduled = false;

    public function __construct(
        private readonly TenantSettingsService                $settings,
        private readonly PushSubscriptionRepositoryInterface  $subscriptions,
        private readonly LoggerInterface                      $logger,
        private readonly string                               $subject,
    ) {}

    public function publicKey(): string
    {
        return $this->keys()['publicKey'];
    }

    public function send(array $userIds, array $payload): void
    {
        try {
            $targets = $this->subscriptions->findByUserIds(array_values(array_unique($userIds)));
        } catch (\Throwable $e) {
            $this->logger->error('Push: cannot load subscriptions', ['error' => $e->getMessage()]);
            return;
        }
        if ($targets === []) {
            return;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($targets as $target) {
            $this->queue[] = ['subscription' => $target, 'payload' => $json];
        }

        if (!$this->flushScheduled) {
            $this->flushScheduled = true;
            register_shutdown_function([$this, 'flush']);
        }
    }

    /** Runs after the HTTP response is sent, so push services never slow down the user's request. */
    public function flush(): void
    {
        if ($this->queue === []) {
            return;
        }
        if (PHP_SAPI === 'fpm-fcgi' && function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $queue       = $this->queue;
        $this->queue = [];

        try {
            $keys    = $this->keys();
            $webPush = new WebPush(
                auth: ['VAPID' => [
                    'subject'    => $this->subject,
                    'publicKey'  => $keys['publicKey'],
                    'privateKey' => $keys['privateKey'],
                ]],
                defaultOptions: ['TTL' => 86400, 'urgency' => 'normal'],
                client: new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5]),
                logger: $this->logger,
            );

            foreach ($queue as $item) {
                $webPush->queueNotification(
                    new Subscription(
                        $item['subscription']['endpoint'],
                        $item['subscription']['p256dh'],
                        $item['subscription']['auth'],
                        ContentEncoding::aes128gcm,
                    ),
                    $item['payload'],
                );
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $this->subscriptions->deleteByEndpoint($report->getEndpoint());
                } elseif (!$report->isSuccess()) {
                    $this->logger->warning('Push: delivery failed', ['reason' => $report->getReason()]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Push: sending failed', ['error' => $e->getMessage()]);
        }
    }

    /** @return array{publicKey: string, privateKey: string} */
    private function keys(): array
    {
        $json = $this->settings->getOrCreateSystemSetting(
            self::SETTING_KEY,
            fn() => json_encode(VAPID::createVapidKeys()),
        );
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
