<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure;

use App\Infrastructure\TokenBucketRateLimiter;
use bandwidthThrottle\tokenBucket\storage\StorageException;
use bandwidthThrottle\tokenBucket\TokenBucket;
use Hyperf\RateLimit\Handler\RateLimitHandler;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TokenBucketRateLimiterTest extends TestCase
{
    use UsesMockery;

    private MockInterface|RateLimitHandler $handler;

    private TokenBucketRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->handler = Mockery::mock(RateLimitHandler::class);
        $this->rateLimiter = new TokenBucketRateLimiter($this->handler);
    }

    #[Test]
    public function returnsTrueWhenBucketAllowsConsumption(): void
    {
        $bucket = Mockery::mock(TokenBucket::class);
        $bucket->shouldReceive('consume')
            ->once()
            ->with(1, null)
            ->andReturn(true);

        $this->handler->shouldReceive('build')
            ->once()
            ->with('test-key', 10, 100, 1)
            ->andReturn($bucket);

        $result = $this->rateLimiter->attempt('test-key', 10, 100, 1);

        $this->assertTrue($result);
    }

    #[Test]
    public function returnsFalseWhenBucketRejectsConsumption(): void
    {
        $bucket = Mockery::mock(TokenBucket::class);
        $bucket->shouldReceive('consume')
            ->once()
            ->with(5, null)
            ->andReturn(false);

        $this->handler->shouldReceive('build')
            ->once()
            ->with('rate-key', 20, 50, 1)
            ->andReturn($bucket);

        $result = $this->rateLimiter->attempt('rate-key', 20, 50, 5);

        $this->assertFalse($result);
    }

    #[Test]
    public function returnsFalseWhenStorageExceptionIsThrown(): void
    {
        $bucket = Mockery::mock(TokenBucket::class);
        $bucket->shouldReceive('consume')
            ->once()
            ->with(1, null)
            ->andThrow(new StorageException('Redis connection failed'));

        $this->handler->shouldReceive('build')
            ->once()
            ->with('failing-key', 10, 100, 1)
            ->andReturn($bucket);

        $result = $this->rateLimiter->attempt('failing-key', 10, 100, 1);

        $this->assertFalse($result);
    }
}
