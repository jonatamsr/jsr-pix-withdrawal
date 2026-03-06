<?php

declare(strict_types=1);

namespace App\Domain\Port;

interface RateLimiterInterface
{
    /**
     * Attempt to consume a token from the given bucket.
     *
     * @param string $key Unique bucket identifier
     * @param int $limit Tokens created per second
     * @param int $capacity Maximum bucket capacity (burst)
     * @param int $consume Tokens consumed per request
     *
     * @return bool True if the request is allowed, false if rate limited
     */
    public function attempt(string $key, int $limit, int $capacity, int $consume): bool;
}
