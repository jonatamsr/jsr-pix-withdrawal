<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Notification;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Mail\SymfonyMailerService;
use App\Infrastructure\Notification\PixWithdrawNotificationStrategy;
use DateTimeImmutable;
use HyperfTest\Support\MocksLogger;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class PixWithdrawNotificationStrategyTest extends TestCase
{
    use MocksLogger;
    use UsesMockery;

    private MockInterface|SymfonyMailerService $mailerService;

    private PixWithdrawNotificationStrategy $strategy;

    protected function setUp(): void
    {
        $this->mailerService = Mockery::mock(SymfonyMailerService::class);
        $this->strategy = new PixWithdrawNotificationStrategy(
            $this->mailerService,
            $this->silentLogger(),
        );
    }

    #[Test]
    public function sendsEmailWhenPixMethodDataProvided(): void
    {
        $withdraw = $this->createWithdraw();
        $pixKey = PixKey::create('email', 'user@example.com');
        $methodData = new PixWithdrawData($pixKey);

        $this->mailerService->shouldReceive('sendWithdrawCompleted')
            ->once()
            ->with(
                $withdraw,
                Mockery::on(fn (AccountWithdrawPix $pix) => $pix->pixKey()->key() === 'user@example.com'
                    && $pix->accountWithdrawId()->value() === $withdraw->id()->value()),
                Mockery::type(DateTimeImmutable::class),
            );

        $this->strategy->notify($withdraw, $methodData, new DateTimeImmutable());
    }

    #[Test]
    public function doesNotSendEmailWhenMethodDataIsNull(): void
    {
        $withdraw = $this->createWithdraw();

        $this->mailerService->shouldNotReceive('sendWithdrawCompleted');

        $this->strategy->notify($withdraw, null, new DateTimeImmutable());
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
