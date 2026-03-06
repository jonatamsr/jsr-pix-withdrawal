<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability\OTel;

use App\Infrastructure\Observability\OTel\OTelScope;
use App\Infrastructure\Observability\OTel\OTelScopeManager;
use HyperfTest\Support\UsesMockery;
use Mockery;
use OpenTracing\Span;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OTelScopeTest extends TestCase
{
    use UsesMockery;

    #[Test]
    public function getSpanReturnsTheWrappedSpan(): void
    {
        $span = Mockery::mock(Span::class);
        $manager = new OTelScopeManager();

        $scope = new OTelScope($span, $manager, false);

        $this->assertSame($span, $scope->getSpan());
    }

    #[Test]
    public function closeDeactivatesScopeFromManager(): void
    {
        $span = Mockery::mock(Span::class);
        $manager = new OTelScopeManager();

        $scope = $manager->activate($span, false);

        $this->assertSame($scope, $manager->getActive());

        $scope->close();

        $this->assertNull($manager->getActive());
    }

    #[Test]
    public function closeFinishesSpanWhenFinishOnCloseIsTrue(): void
    {
        $span = Mockery::mock(Span::class);
        $span->shouldReceive('finish')->once();

        $manager = new OTelScopeManager();
        $scope = new OTelScope($span, $manager, true);

        $scope->close();
    }

    #[Test]
    public function closeDoesNotFinishSpanWhenFinishOnCloseIsFalse(): void
    {
        $span = Mockery::mock(Span::class);
        $span->shouldNotReceive('finish');

        $manager = new OTelScopeManager();
        $scope = new OTelScope($span, $manager, false);

        $scope->close();
    }
}
