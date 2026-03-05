<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\BusinessException;
use App\Domain\Exception\InvalidScheduleDateException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InvalidScheduleDateExceptionTest extends TestCase
{
    #[Test]
    public function hasCorrectMessage(): void
    {
        $exception = new InvalidScheduleDateException();

        $this->assertSame('The schedule date must be in the future', $exception->getMessage());
    }

    #[Test]
    public function hasCorrectErrorCode(): void
    {
        $exception = new InvalidScheduleDateException();

        $this->assertSame('INVALID_SCHEDULE_DATE', $exception->getErrorCode());
    }

    #[Test]
    public function extendsBusinessException(): void
    {
        $exception = new InvalidScheduleDateException();

        $this->assertInstanceOf(BusinessException::class, $exception);
    }

    #[Test]
    public function hasDefaultHttpStatusCode(): void
    {
        $exception = new InvalidScheduleDateException();

        $this->assertSame(422, $exception->getHttpStatusCode());
    }
}
