<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Cache;

/**
 * No-op rate limiter for community mode (no Redis available).
 * Always allows every request.
 */
final class NullRateLimiter extends RedisRateLimiter
{
    public function __construct()
    {
        // Do not call parent — no Redis client needed
    }

    public function isAllowed(string $key): bool
    {
        return true;
    }

    public function getRemainingRequests(string $key): int
    {
        return PHP_INT_MAX;
    }
}
