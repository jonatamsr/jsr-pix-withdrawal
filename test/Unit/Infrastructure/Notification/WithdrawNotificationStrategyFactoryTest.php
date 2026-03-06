<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Notification;

use App\Domain\Enum\WithdrawMethod;
use App\Infrastructure\Notification\PixWithdrawNotificationStrategy;
use App\Infrastructure\Notification\WithdrawNotificationStrategyFactory;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
class WithdrawNotificationStrategyFactoryTest extends TestCase
{
    use UsesMockery;

    private ContainerInterface|MockInterface $container;

    private WithdrawNotificationStrategyFactory $factory;

    protected function setUp(): void
    {
        $this->container = Mockery::mock(ContainerInterface::class);
        $this->factory = new WithdrawNotificationStrategyFactory($this->container);
    }

    #[Test]
    public function createReturnsPixStrategyForPixMethod(): void
    {
        $strategy = Mockery::mock(PixWithdrawNotificationStrategy::class);

        $this->container->shouldReceive('get')
            ->once()
            ->with(PixWithdrawNotificationStrategy::class)
            ->andReturn($strategy);

        $result = $this->factory->create(WithdrawMethod::PIX);

        $this->assertSame($strategy, $result);
    }
}
