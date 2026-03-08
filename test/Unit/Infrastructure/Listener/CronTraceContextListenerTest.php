<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Listener;

use App\Infrastructure\Listener\CronTraceContextListener;
use Hyperf\Context\Context;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Event\AfterExecute;
use Hyperf\Crontab\Event\BeforeExecute;
use Hyperf\Crontab\Event\FailToExecute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
class CronTraceContextListenerTest extends TestCase
{
    private CronTraceContextListener $listener;

    protected function setUp(): void
    {
        $this->listener = new CronTraceContextListener();
    }

    protected function tearDown(): void
    {
        Context::destroy('trace_id');
    }

    #[Test]
    public function listenReturnsExpectedEvents(): void
    {
        $this->assertSame(
            [BeforeExecute::class, AfterExecute::class, FailToExecute::class],
            $this->listener->listen(),
        );
    }

    #[Test]
    public function setsTraceIdAndRequestIdOnBeforeExecute(): void
    {
        $event = new BeforeExecute(new Crontab());

        $this->listener->process($event);

        $traceId = Context::get('trace_id');

        $this->assertNotEmpty($traceId);
        $this->assertTrue(Uuid::isValid($traceId));
    }

    #[Test]
    public function generatesUniqueTraceIdPerExecution(): void
    {
        $event = new BeforeExecute(new Crontab());

        $this->listener->process($event);
        $firstTraceId = Context::get('trace_id');

        $this->listener->process($event);
        $secondTraceId = Context::get('trace_id');

        $this->assertNotSame($firstTraceId, $secondTraceId);
    }

    #[Test]
    public function clearsContextOnAfterExecute(): void
    {
        Context::set('trace_id', 'some-trace-id');

        $event = new AfterExecute(new Crontab());

        $this->listener->process($event);

        $this->assertSame('', Context::get('trace_id', ''));
    }

    #[Test]
    public function clearsContextOnFailToExecute(): void
    {
        Context::set('trace_id', 'some-trace-id');

        $event = new FailToExecute(new Crontab(), new RuntimeException('cron failed'));

        $this->listener->process($event);

        $this->assertSame('', Context::get('trace_id', ''));
    }

    #[Test]
    public function ignoresUnrelatedEvents(): void
    {
        $this->listener->process(new stdClass());

        $this->assertSame('', Context::get('trace_id', ''));
        $this->assertSame('', Context::get('request_id', ''));
    }

    #[Test]
    public function fullLifecycleSetsAndClearsContext(): void
    {
        $crontab = new Crontab();

        $this->listener->process(new BeforeExecute($crontab));

        $traceId = Context::get('trace_id');
        $this->assertNotEmpty($traceId);
        $this->assertTrue(Uuid::isValid($traceId));

        $this->listener->process(new AfterExecute($crontab));

        $this->assertSame('', Context::get('trace_id', ''));
    }
}
