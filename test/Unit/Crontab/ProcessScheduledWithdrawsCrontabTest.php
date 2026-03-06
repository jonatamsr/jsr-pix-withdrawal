<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Crontab;

use App\Application\UseCase\ProcessScheduledWithdrawsUseCase;
use App\Crontab\ProcessScheduledWithdrawsCrontab;
use HyperfTest\Support\MocksLogger;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class ProcessScheduledWithdrawsCrontabTest extends TestCase
{
    use MocksLogger;
    use UsesMockery;

    private MockInterface|ProcessScheduledWithdrawsUseCase $useCase;

    private ProcessScheduledWithdrawsCrontab $crontab;

    protected function setUp(): void
    {
        $this->useCase = Mockery::mock(ProcessScheduledWithdrawsUseCase::class);

        $this->crontab = new ProcessScheduledWithdrawsCrontab(
            $this->useCase,
            $this->silentLogger(),
        );
    }

    #[Test]
    public function delegatesToUseCase(): void
    {
        $this->useCase
            ->shouldReceive('execute')
            ->once();

        $this->crontab->execute();
    }

    #[Test]
    public function catchesExceptionsWithoutPropagating(): void
    {
        $this->useCase
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new RuntimeException('something went wrong'));

        $this->crontab->execute();

        $this->addToAssertionCount(1);
    }
}
