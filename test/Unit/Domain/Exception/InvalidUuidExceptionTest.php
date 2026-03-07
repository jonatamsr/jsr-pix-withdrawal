<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\InvalidUuidException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InvalidUuidExceptionTest extends TestCase
{
    #[Test]
    public function hasCorrectErrorCode(): void
    {
        $exception = new InvalidUuidException('invalid');

        $this->assertSame('INVALID_UUID', $exception->getErrorCode());
    }

    #[Test]
    public function hasOverriddenHttpStatusCode(): void
    {
        $exception = new InvalidUuidException('invalid');

        $this->assertSame(400, $exception->getHttpStatusCode());
    }
}
