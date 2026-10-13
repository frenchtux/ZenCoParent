<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\Notification;

interface PushSenderInterface
{
    public function publicKey(): string;

    /**
     * Sends to every device registered by these users. Never throws: a failed
     * notification must not fail the request that triggered it.
     *
     * @param string[] $userIds
     * @param array{title: string, body: string, url: string, tag?: string} $payload
     */
    public function send(array $userIds, array $payload): void;
}
