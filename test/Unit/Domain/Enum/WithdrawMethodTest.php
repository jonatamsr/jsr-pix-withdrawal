<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Enum;

use App\Domain\Enum\WithdrawMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * @internal
 * @coversNothing
 */
class WithdrawMethodTest extends TestCase
{
    #[Test]
    public function pixCaseHasCorrectValue(): void
    {
        $this->assertSame('pix', WithdrawMethod::PIX->value);
    }

    #[Test]
    public function fromStringResolvesPixCase(): void
    {
        $method = WithdrawMethod::from('pix');

        $this->assertSame(WithdrawMethod::PIX, $method);
    }

    #[Test]
    public function tryFromReturnsNullForUnsupportedMethod(): void
    {
        $method = WithdrawMethod::tryFrom('TED');

        $this->assertNull($method);
    }

    #[Test]
    public function fromThrowsOnUnsupportedMethod(): void
    {
        $this->expectException(ValueError::class);

        WithdrawMethod::from('BOLETO');
    }
}
