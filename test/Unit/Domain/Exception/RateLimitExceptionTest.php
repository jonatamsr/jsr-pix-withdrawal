<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\BusinessException;
use App\Domain\Exception\RateLimitException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RateLimitExceptionTest extends TestCase
{
    #[Test]
    public function hasCorrectMessage(): void
    {
        $exception = new RateLimitException();

        $this->assertSame('Too many requests. Please try again later', $exception->getMessage());
    }

    #[Test]
    public function hasCorrectErrorCode(): void
    {
        $exception = new RateLimitException();

        $this->assertSame('RATE_LIMIT_EXCEEDED', $exception->getErrorCode());
    }

    #[Test]
    public function extendsBusinessException(): void
    {
        $exception = new RateLimitException();

        $this->assertInstanceOf(BusinessException::class, $exception);
    }

    #[Test]
    public function hasHttpStatusCode429(): void
    {
        $exception = new RateLimitException();

        $this->assertSame(429, $exception->getHttpStatusCode());
    }
}
