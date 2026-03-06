<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\BusinessException;
use App\Domain\Exception\InvalidWithdrawMethodException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InvalidWithdrawMethodExceptionTest extends TestCase
{
    #[Test]
    public function hasCorrectMessage(): void
    {
        $exception = new InvalidWithdrawMethodException('TED');

        $this->assertSame('The withdraw method TED is not supported', $exception->getMessage());
    }

    #[Test]
    public function hasCorrectErrorCode(): void
    {
        $exception = new InvalidWithdrawMethodException('TED');

        $this->assertSame('INVALID_WITHDRAW_METHOD', $exception->getErrorCode());
    }

    #[Test]
    public function extendsBusinessException(): void
    {
        $exception = new InvalidWithdrawMethodException('TED');

        $this->assertInstanceOf(BusinessException::class, $exception);
    }

    #[Test]
    public function hasHttpStatusCode400(): void
    {
        $exception = new InvalidWithdrawMethodException('TED');

        $this->assertSame(400, $exception->getHttpStatusCode());
    }
}
