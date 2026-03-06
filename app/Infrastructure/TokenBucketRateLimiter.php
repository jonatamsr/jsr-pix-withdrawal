<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Port\RateLimiterInterface;
use bandwidthThrottle\tokenBucket\storage\StorageException;
use Hyperf\RateLimit\Handler\RateLimitHandler;

class TokenBucketRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly RateLimitHandler $handler,
    ) {
    }

    public function attempt(string $key, int $limit, int $capacity, int $consume): bool
    {
        $bucket = $this->handler->build($key, $limit, $capacity, 1);

        try {
            return $bucket->consume($consume, $seconds);
        } catch (StorageException) {
            return false;
        }
    }
}
