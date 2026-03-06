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
    public function inThePastHasCorrectMessage(): void
    {
        $exception = InvalidScheduleDateException::inThePast();

        $this->assertSame('The schedule date must be in the future', $exception->getMessage());
    }

    #[Test]
    public function invalidFormatHasCorrectMessage(): void
    {
        $exception = InvalidScheduleDateException::invalidFormat('not-a-date');

        $this->assertSame('Invalid schedule date format: not-a-date. Expected format: Y-m-d H:i', $exception->getMessage());
    }

    #[Test]
    public function hasCorrectErrorCode(): void
    {
        $exception = InvalidScheduleDateException::inThePast();

        $this->assertSame('INVALID_SCHEDULE_DATE', $exception->getErrorCode());
    }

    #[Test]
    public function extendsBusinessException(): void
    {
        $exception = InvalidScheduleDateException::inThePast();

        $this->assertInstanceOf(BusinessException::class, $exception);
    }

    #[Test]
    public function hasDefaultHttpStatusCode(): void
    {
        $exception = InvalidScheduleDateException::inThePast();

        $this->assertSame(422, $exception->getHttpStatusCode());
    }
}
