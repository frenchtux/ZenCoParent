<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Cache;

final class RedisRateLimiter
{
    public function __construct(
        private \Predis\Client $redis,
        private int $maxRequests = 60,
        private int $windowSeconds = 60,
    ) {}

    public function isAllowed(string $key): bool
    {
        $now         = microtime(true);
        $windowStart = $now - $this->windowSeconds;
        $redisKey    = "ratelimit:{$key}";

        $this->redis->multi();
        $this->redis->zremrangebyscore($redisKey, '-inf', (string) $windowStart);
        $this->redis->zadd($redisKey, [$now => (string) $now]);
        $this->redis->zcard($redisKey);
        $this->redis->expire($redisKey, $this->windowSeconds * 2);
        $results = $this->redis->exec();

        $count = $results[2];
        return $count <= $this->maxRequests;
    }

    public function getRemainingRequests(string $key): int
    {
        $now         = microtime(true);
        $windowStart = $now - $this->windowSeconds;
        $redisKey    = "ratelimit:{$key}";
        $count = $this->redis->zcount($redisKey, (string) $windowStart, '+inf');
        return max(0, $this->maxRequests - (int) $count);
    }
}
