<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class MoneyTest extends TestCase
{
    // -- Construction: fromFloat --

    #[Test]
    public function fromFloatCreatesWithValidAmount(): void
    {
        $money = Money::fromFloat(150.75);

        $this->assertSame('150.75', $money->toDecimal());
    }

    #[Test]
    public function fromFloatWithWholeNumber(): void
    {
        $money = Money::fromFloat(100.00);

        $this->assertSame('100.00', $money->toDecimal());
    }

    #[Test]
    public function fromFloatWithOneCentPrecision(): void
    {
        $money = Money::fromFloat(0.01);

        $this->assertSame('0.01', $money->toDecimal());
    }

    #[Test]
    public function fromFloatAcceptsZero(): void
    {
        $money = Money::fromFloat(0.00);

        $this->assertSame('0.00', $money->toDecimal());
    }

    #[Test]
    public function fromFloatThrowsOnNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('negative');

        Money::fromFloat(-10.00);
    }

    #[Test]
    public function fromFloatThrowsOnMoreThanTwoDecimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('2 decimal places');

        Money::fromFloat(10.123);
    }

    // -- Construction: fromString --

    #[Test]
    public function fromStringCreatesWithValidAmount(): void
    {
        $money = Money::fromString('250.50');

        $this->assertSame('250.50', $money->toDecimal());
    }

    #[Test]
    public function fromStringWithWholeNumber(): void
    {
        $money = Money::fromString('100');

        $this->assertSame('100.00', $money->toDecimal());
    }

    #[Test]
    public function fromStringWithLeftZeros(): void
    {
        $money = Money::fromString('00100');

        $this->assertSame('100.00', $money->toDecimal());
    }

    #[Test]
    public function fromStringWithOneCent(): void
    {
        $money = Money::fromString('0.01');

        $this->assertSame('0.01', $money->toDecimal());
    }

    #[Test]
    public function fromStringThrowsOnNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid money format');

        Money::fromString('abc');
    }

    #[Test]
    public function fromStringThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid money format');

        Money::fromString('');
    }

    #[Test]
    public function fromStringThrowsOnNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid money format');

        Money::fromString('-5.00');
    }

    #[Test]
    public function fromStringThrowsOnMoreThanTwoDecimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid money format');

        Money::fromString('10.123');
    }

    #[Test]
    public function fromStringAcceptsZero(): void
    {
        $money = Money::fromString('0');

        $this->assertSame('0.00', $money->toDecimal());
    }

    // -- zero() factory --

    #[Test]
    public function zeroFactoryCreatesZeroMoney(): void
    {
        $money = Money::zero();

        $this->assertSame('0.00', $money->toDecimal());
    }

    // -- Immutability --

    #[Test]
    public function subtractReturnsNewInstance(): void
    {
        $a = Money::fromFloat(100.00);
        $b = Money::fromFloat(30.00);
        $result = $a->subtract($b);

        $this->assertSame('100.00', $a->toDecimal());
        $this->assertSame('30.00', $b->toDecimal());
        $this->assertSame('70.00', $result->toDecimal());
    }

    // -- subtract() --

    #[Test]
    public function subtractWithValidAmounts(): void
    {
        $a = Money::fromFloat(200.50);
        $b = Money::fromFloat(50.25);
        $result = $a->subtract($b);

        $this->assertSame('150.25', $result->toDecimal());
    }

    #[Test]
    public function subtractReturnsZeroWhenEqual(): void
    {
        $a = Money::fromFloat(100.00);
        $b = Money::fromFloat(100.00);
        $result = $a->subtract($b);

        $this->assertSame('0.00', $result->toDecimal());
    }

    #[Test]
    public function subtractThrowsWhenResultWouldBeNegative(): void
    {
        $a = Money::fromFloat(50.00);
        $b = Money::fromFloat(100.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subtraction would result in negative value: 50.00 - 100.00 = -50.00');

        $a->subtract($b);
    }

    #[Test]
    public function subtractWithSmallDifference(): void
    {
        $a = Money::fromFloat(100.00);
        $b = Money::fromFloat(99.99);
        $result = $a->subtract($b);

        $this->assertSame('0.01', $result->toDecimal());
    }

    // -- isGreaterThanOrEqual() --

    #[Test]
    public function isGreaterThanOrEqualWhenGreater(): void
    {
        $a = Money::fromFloat(200.00);
        $b = Money::fromFloat(100.00);

        $this->assertTrue($a->isGreaterThanOrEqual($b));
    }

    #[Test]
    public function isGreaterThanOrEqualWhenEqual(): void
    {
        $a = Money::fromFloat(100.00);
        $b = Money::fromFloat(100.00);

        $this->assertTrue($a->isGreaterThanOrEqual($b));
    }

    #[Test]
    public function isGreaterThanOrEqualWhenLessThan(): void
    {
        $a = Money::fromFloat(50.00);
        $b = Money::fromFloat(100.00);

        $this->assertFalse($a->isGreaterThanOrEqual($b));
    }

    // -- toDecimal() --

    #[Test]
    public function toDecimalAlwaysReturnsTwoDecimals(): void
    {
        $money = Money::fromFloat(150.10);

        $this->assertSame('150.10', $money->toDecimal());
    }

    // -- Cross-constructor consistency --

    #[Test]
    public function fromFloatAndFromStringProduceSameResult(): void
    {
        $a = Money::fromFloat(50.00);
        $b = Money::fromString('50.00');

        $this->assertSame($a->toDecimal(), $b->toDecimal());
    }

    // -- Large amounts --

    #[Test]
    public function handlesLargeAmountsFromFloat(): void
    {
        $money = Money::fromFloat(999999.99);

        $this->assertSame('999999.99', $money->toDecimal());
    }

    #[Test]
    public function handlesLargeAmountsFromString(): void
    {
        $money = Money::fromString('9999999999');

        $this->assertSame('9999999999.00', $money->toDecimal());
    }
}
