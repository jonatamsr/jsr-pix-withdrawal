<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\InvalidAmountException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InvalidAmountExceptionTest extends TestCase
{
    #[Test]
    public function negativeHasCorrectErrorCode(): void
    {
        $exception = InvalidAmountException::negative();

        $this->assertSame('INVALID_AMOUNT', $exception->getErrorCode());
    }

    #[Test]
    public function tooManyDecimalsHasCorrectErrorCode(): void
    {
        $exception = InvalidAmountException::tooManyDecimals();

        $this->assertSame('INVALID_AMOUNT', $exception->getErrorCode());
    }

    #[Test]
    public function invalidFormatHasCorrectErrorCode(): void
    {
        $exception = InvalidAmountException::invalidFormat('abc');

        $this->assertSame('INVALID_AMOUNT', $exception->getErrorCode());
    }

    #[Test]
    public function mustBePositiveHasCorrectErrorCode(): void
    {
        $exception = InvalidAmountException::mustBePositive();

        $this->assertSame('INVALID_AMOUNT', $exception->getErrorCode());
    }
}
