<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Messaging;

final readonly class CreateThreadCommand
{
    public function __construct(
        public string $tenantId,
        public string $type,
        public string $createdBy,
        public array  $participantIds = [],
    ) {}
}
