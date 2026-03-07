<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Event;

use App\Domain\Event\DomainEvent;
use App\Infrastructure\Event\HyperfEventDispatcherAdapter;
use DateTimeImmutable;
use HyperfTest\Support\UsesMockery;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

/**
 * @internal
 */
class HyperfEventDispatcherAdapterTest extends TestCase
{
    use UsesMockery;

    private Mockery\MockInterface|PsrEventDispatcherInterface $psrDispatcher;

    private HyperfEventDispatcherAdapter $adapter;

    protected function setUp(): void
    {
        $this->psrDispatcher = Mockery::mock(PsrEventDispatcherInterface::class);
        $this->adapter = new HyperfEventDispatcherAdapter($this->psrDispatcher);
    }

    #[Test]
    public function dispatchDelegatesEventToPsrDispatcher(): void
    {
        $event = $this->createDomainEvent();

        $this->psrDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with($event)
            ->andReturn($event);

        $this->adapter->dispatch($event);
    }

    private function createDomainEvent(): DomainEvent
    {
        return new class implements DomainEvent {
            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-03-07T00:00:00+00:00');
            }
        };
    }
}
