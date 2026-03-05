<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\AccountNotFoundException;
use App\Domain\Exception\BusinessException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AccountNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function hasCorrectMessage(): void
    {
        $exception = new AccountNotFoundException('abc-123');

        $this->assertSame('Account with ID abc-123 was not found', $exception->getMessage());
    }

    #[Test]
    public function hasCorrectErrorCode(): void
    {
        $exception = new AccountNotFoundException('abc-123');

        $this->assertSame('ACCOUNT_NOT_FOUND', $exception->getErrorCode());
    }

    #[Test]
    public function extendsBusinessException(): void
    {
        $exception = new AccountNotFoundException('abc-123');

        $this->assertInstanceOf(BusinessException::class, $exception);
    }

    #[Test]
    public function hasOverriddenHttpStatusCode(): void
    {
        $exception = new AccountNotFoundException('abc-123');

        $this->assertSame(404, $exception->getHttpStatusCode());
    }
}
