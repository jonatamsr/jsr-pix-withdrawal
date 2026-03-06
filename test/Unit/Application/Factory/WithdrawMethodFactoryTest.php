<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Application\Factory;

use App\Application\Factory\WithdrawMethodFactory;
use App\Domain\Exception\InvalidWithdrawMethodException;
use App\Domain\Strategy\PixWithdrawStrategy;
use App\Domain\Strategy\WithdrawMethodStrategyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class WithdrawMethodFactoryTest extends TestCase
{
    private WithdrawMethodFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new WithdrawMethodFactory();
    }

    // -- Known methods --

    #[Test]
    public function createsPixStrategyForPIX(): void
    {
        $strategy = $this->factory->create('PIX');

        $this->assertInstanceOf(WithdrawMethodStrategyInterface::class, $strategy);
        $this->assertInstanceOf(PixWithdrawStrategy::class, $strategy);
    }

    #[Test]
    public function normalizesMethodToLowercase(): void
    {
        $strategy = $this->factory->create('pix');

        $this->assertInstanceOf(PixWithdrawStrategy::class, $strategy);
    }

    #[Test]
    public function trimsWhitespaceFromMethod(): void
    {
        $strategy = $this->factory->create('  PIX  ');

        $this->assertInstanceOf(PixWithdrawStrategy::class, $strategy);
    }

    #[Test]
    public function returnsNewInstanceOnEachCall(): void
    {
        $first = $this->factory->create('PIX');
        $second = $this->factory->create('PIX');

        $this->assertNotSame($first, $second);
    }

    // -- Unknown methods --

    #[Test]
    #[DataProvider('unsupportedMethodsProvider')]
    public function throwsForUnsupportedMethod(string $method): void
    {
        $this->expectException(InvalidWithdrawMethodException::class);
        $this->expectExceptionMessage("The withdraw method {$method} is not supported");

        $this->factory->create($method);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedMethodsProvider(): iterable
    {
        yield 'TED' => ['TED'];
        yield 'BOLETO' => ['BOLETO'];
        yield 'empty string' => [''];
        yield 'random string' => ['UNKNOWN'];
    }
}
