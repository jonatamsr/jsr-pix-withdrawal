<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability\OTel;

use App\Infrastructure\Observability\OTel\OTelScopeManager;
use HyperfTest\Support\UsesMockery;
use Mockery;
use OpenTracing\Span;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OTelScopeManagerTest extends TestCase
{
    use UsesMockery;

    private OTelScopeManager $manager;

    protected function setUp(): void
    {
        $this->manager = new OTelScopeManager();
    }

    #[Test]
    public function getActiveReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->manager->getActive());
    }

    #[Test]
    public function activateReturnsScopeAndSetsActive(): void
    {
        $span = Mockery::mock(Span::class);

        $scope = $this->manager->activate($span);

        $this->assertSame($scope, $this->manager->getActive());
        $this->assertSame($span, $scope->getSpan());
    }

    #[Test]
    public function nestedScopesWorkAsStack(): void
    {
        $span1 = Mockery::mock(Span::class);
        $span2 = Mockery::mock(Span::class);

        $scope1 = $this->manager->activate($span1, false);
        $scope2 = $this->manager->activate($span2, false);

        $this->assertSame($scope2, $this->manager->getActive());

        $scope2->close();
        $this->assertSame($scope1, $this->manager->getActive());

        $scope1->close();
        $this->assertNull($this->manager->getActive());
    }

    #[Test]
    public function deactivateMiddleScopePreservesOrder(): void
    {
        $span1 = Mockery::mock(Span::class);
        $span2 = Mockery::mock(Span::class);
        $span3 = Mockery::mock(Span::class);

        $scope1 = $this->manager->activate($span1, false);
        $scope2 = $this->manager->activate($span2, false);
        $scope3 = $this->manager->activate($span3, false);

        // Deactivate the middle scope
        $this->manager->deactivate($scope2);

        // Top should still be scope3
        $this->assertSame($scope3, $this->manager->getActive());

        $scope3->close();
        $this->assertSame($scope1, $this->manager->getActive());
    }

    #[Test]
    public function activateWithFinishSpanOnCloseFinishesSpanOnScopeClose(): void
    {
        $span = Mockery::mock(Span::class);
        $span->shouldReceive('finish')->once();

        $scope = $this->manager->activate($span, true);
        $scope->close();

        $this->assertNull($this->manager->getActive());
    }

    #[Test]
    public function activateWithoutFinishSpanOnCloseDoesNotFinishSpan(): void
    {
        $span = Mockery::mock(Span::class);
        $span->shouldNotReceive('finish');

        $scope = $this->manager->activate($span, false);
        $scope->close();

        $this->assertNull($this->manager->getActive());
    }
}
