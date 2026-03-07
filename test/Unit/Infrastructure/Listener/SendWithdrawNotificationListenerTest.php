<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Listener;

use App\Domain\Entity\Account;
use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Event\WithdrawCompleted;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Listener\SendWithdrawNotificationListener;
use App\Infrastructure\Notification\WithdrawNotificationStrategyFactory;
use App\Infrastructure\Notification\WithdrawNotificationStrategyInterface;
use DateTimeImmutable;
use HyperfTest\Support\MocksLogger;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
class SendWithdrawNotificationListenerTest extends TestCase
{
    use MocksLogger;
    use UsesMockery;

    private MockInterface|WithdrawNotificationStrategyFactory $strategyFactory;

    private SendWithdrawNotificationListener $listener;

    protected function setUp(): void
    {
        $this->strategyFactory = Mockery::mock(WithdrawNotificationStrategyFactory::class);

        $this->listener = new SendWithdrawNotificationListener(
            $this->strategyFactory,
            $this->silentLogger(),
        );
    }

    #[Test]
    public function listenReturnsWithdrawCompletedEvent(): void
    {
        $this->assertSame([WithdrawCompleted::class], $this->listener->listen());
    }

    #[Test]
    public function delegatesToStrategyWhenFound(): void
    {
        $withdraw = $this->createWithdraw();
        $methodData = new PixWithdrawData(PixKey::create('email', 'user@example.com'));
        $event = new WithdrawCompleted($withdraw, $this->createAccount(), $methodData);

        $strategy = Mockery::mock(WithdrawNotificationStrategyInterface::class);
        $strategy->shouldReceive('notify')->once()->with($withdraw, $methodData, Mockery::type(DateTimeImmutable::class));

        $this->strategyFactory->shouldReceive('create')
            ->once()
            ->with(WithdrawMethod::PIX)
            ->andReturn($strategy);

        $this->listener->process($event);
    }

    #[Test]
    public function doesNothingWhenNoStrategyFound(): void
    {
        $withdraw = $this->createWithdraw();
        $event = new WithdrawCompleted($withdraw, $this->createAccount());

        $this->strategyFactory->shouldReceive('create')
            ->once()
            ->andReturnNull();

        $this->listener->process($event);
    }

    #[Test]
    public function doesNotBreakWhenStrategyThrowsException(): void
    {
        $withdraw = $this->createWithdraw();
        $event = new WithdrawCompleted($withdraw, $this->createAccount());

        $strategy = Mockery::mock(WithdrawNotificationStrategyInterface::class);
        $strategy->shouldReceive('notify')
            ->once()
            ->andThrow(new RuntimeException('SMTP connection failed'));

        $this->strategyFactory->shouldReceive('create')
            ->once()
            ->andReturn($strategy);

        $this->listener->process($event);

        $this->assertTrue(true, 'Listener should not propagate exceptions');
    }

    #[Test]
    public function ignoresNonWithdrawCompletedEvents(): void
    {
        $this->strategyFactory->shouldNotReceive('create');

        $this->listener->process(new stdClass());
    }

    private function createWithdraw(): AccountWithdraw
    {
        return AccountWithdraw::createImmediate(
            Uuid::generate(),
            Uuid::generate(),
            WithdrawMethod::PIX,
            Money::fromFloat(150.75),
        );
    }

    private function createAccount(): Account
    {
        return Account::create(
            Uuid::generate(),
            'John Doe',
            Money::fromFloat(1000.00),
        );
    }
}
