<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Listener;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Event\WithdrawFailed;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Listener\LogWithdrawFailedListener;
use Hyperf\Contract\StdoutLoggerInterface;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
class LogWithdrawFailedListenerTest extends TestCase
{
    use UsesMockery;

    private MockInterface|StdoutLoggerInterface $logger;

    private LogWithdrawFailedListener $listener;

    protected function setUp(): void
    {
        $this->logger = Mockery::mock(StdoutLoggerInterface::class);

        $this->listener = new LogWithdrawFailedListener($this->logger);
    }

    #[Test]
    public function listenReturnsWithdrawFailedEvent(): void
    {
        $this->assertSame([WithdrawFailed::class], $this->listener->listen());
    }

    #[Test]
    public function logsFailureWithStructuredContext(): void
    {
        $withdraw = $this->createWithdraw();
        $event = new WithdrawFailed($withdraw, 'Insufficient balance');

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Withdraw failed', Mockery::on(function (array $context) use ($withdraw): bool {
                $this->assertSame($withdraw->id()->value(), $context['withdraw_id']);
                $this->assertSame($withdraw->accountId()->value(), $context['account_id']);
                $this->assertSame('pix', $context['method']);
                $this->assertSame('150.75', $context['amount']);
                $this->assertSame('Insufficient balance', $context['reason']);
                $this->assertArrayHasKey('occurred_at', $context);

                return true;
            }));

        $this->listener->process($event);
    }

    #[Test]
    public function ignoresNonWithdrawFailedEvents(): void
    {
        $this->logger->shouldNotReceive('error');

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
}
