<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Exception;

use App\Domain\Exception\InvalidPixDataException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InvalidPixDataExceptionTest extends TestCase
{
    #[Test]
    public function emptyKeyHasCorrectErrorCode(): void
    {
        $exception = InvalidPixDataException::emptyKey();

        $this->assertSame('INVALID_PIX_DATA', $exception->getErrorCode());
    }

    #[Test]
    public function invalidTypeHasCorrectErrorCode(): void
    {
        $exception = InvalidPixDataException::invalidType('UNKNOWN');

        $this->assertSame('INVALID_PIX_DATA', $exception->getErrorCode());
    }

    #[Test]
    public function invalidEmailHasCorrectErrorCode(): void
    {
        $exception = InvalidPixDataException::invalidEmail('not-an-email');

        $this->assertSame('INVALID_PIX_DATA', $exception->getErrorCode());
    }
}
