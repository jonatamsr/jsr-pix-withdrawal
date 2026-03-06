<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability\OTel;

use App\Infrastructure\Observability\OTel\OTelTracer;
use App\Infrastructure\Observability\OTel\OTelTracerFactory;
use Hyperf\Contract\ConfigInterface;
use HyperfTest\Support\UsesMockery;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OTelTracerFactoryTest extends TestCase
{
    use UsesMockery;

    #[Test]
    public function makeReturnsOTelTracerInstance(): void
    {
        $config = Mockery::mock(ConfigInterface::class);
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.endpoint', Mockery::any())
            ->andReturn('http://localhost:4318/v1/traces');
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.content_type', Mockery::any())
            ->andReturn('application/json');
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.service_name', Mockery::any())
            ->andReturn('test-service');
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.processor', Mockery::any())
            ->andReturn('simple');
        $config->shouldReceive('get')
            ->with('app_name', Mockery::any())
            ->andReturn('test-service');
        $config->shouldReceive('get')
            ->with('app_env', Mockery::any())
            ->andReturn('testing');

        $factory = new OTelTracerFactory($config);
        $tracer = $factory->make('otel');

        $this->assertInstanceOf(OTelTracer::class, $tracer);
    }

    #[Test]
    public function makeWithBatchProcessorReturnsTracer(): void
    {
        $config = Mockery::mock(ConfigInterface::class);
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.endpoint', Mockery::any())
            ->andReturn('http://localhost:4318/v1/traces');
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.content_type', Mockery::any())
            ->andReturn('application/json');
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.service_name', Mockery::any())
            ->andReturn('batch-service');
        $config->shouldReceive('get')
            ->with('opentracing.tracer.otel.processor', Mockery::any())
            ->andReturn('batch');
        $config->shouldReceive('get')
            ->with('app_name', Mockery::any())
            ->andReturn('batch-service');
        $config->shouldReceive('get')
            ->with('app_env', Mockery::any())
            ->andReturn('testing');

        $factory = new OTelTracerFactory($config);
        $tracer = $factory->make('otel');

        $this->assertInstanceOf(OTelTracer::class, $tracer);
    }
}
