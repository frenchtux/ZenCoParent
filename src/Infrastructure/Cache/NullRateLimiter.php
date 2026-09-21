<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Cache;

/**
 * No-op rate limiter used when Redis is not configured.
 * Always allows every request.
 */
final class NullRateLimiter implements RateLimiterInterface
{
    public function isAllowed(string $key): bool
    {
        return true;
    }

    public function getRemainingRequests(string $key): int
    {
        return PHP_INT_MAX;
    }
}
