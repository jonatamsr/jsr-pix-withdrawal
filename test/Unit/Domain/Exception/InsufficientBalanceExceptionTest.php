<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\BusinessException;
use App\Domain\Exception\InsufficientBalanceException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InsufficientBalanceExceptionTest extends TestCase
{
    #[Test]
    public function hasCorrectMessage(): void
    {
        $exception = new InsufficientBalanceException();

        $this->assertSame('Insufficient balance to complete this withdrawal', $exception->getMessage());
    }

    #[Test]
    public function hasCorrectErrorCode(): void
    {
        $exception = new InsufficientBalanceException();

        $this->assertSame('INSUFFICIENT_BALANCE', $exception->getErrorCode());
    }

    #[Test]
    public function extendsBusinessException(): void
    {
        $exception = new InsufficientBalanceException();

        $this->assertInstanceOf(BusinessException::class, $exception);
    }
}
