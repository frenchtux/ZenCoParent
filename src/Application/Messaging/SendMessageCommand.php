<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

final readonly class SendMessageCommand
{
    public function __construct(
        public string $threadId,
        public string $tenantId,
        public string $senderId,
        public string $content,
    ) {}
}
