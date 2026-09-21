<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Cache;

interface RateLimiterInterface
{
    public function isAllowed(string $key): bool;

    public function getRemainingRequests(string $key): int;
}
