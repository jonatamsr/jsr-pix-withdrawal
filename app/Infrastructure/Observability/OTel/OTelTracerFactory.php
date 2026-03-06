<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\OTel;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Tracer\Contract\NamedFactoryInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTracing\Tracer;

final class OTelTracerFactory implements NamedFactoryInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
    ) {
    }

    public function make(string $name): Tracer
    {
        $prefix = "opentracing.tracer.{$name}";

        $endpoint = $this->config->get("{$prefix}.endpoint", 'http://jaeger:4318/v1/traces');
        $contentType = $this->config->get("{$prefix}.content_type", 'application/json');
        $serviceName = $this->config->get("{$prefix}.service_name", $this->config->get('app_name', 'jsr-pix-withdrawal'));
        $processorType = $this->config->get("{$prefix}.processor", 'simple');

        // Build OTLP HTTP transport
        $transport = (new OtlpHttpTransportFactory())->create(
            $endpoint,
            $contentType,
        );

        $exporter = new SpanExporter($transport);

        // Choose span processor
        $processor = match ($processorType) {
            'batch' => (new BatchSpanProcessorBuilder($exporter))->build(),
            default => new SimpleSpanProcessor($exporter),
        };

        // Build resource with service metadata
        $resource = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                'service.name' => $serviceName,
                'service.version' => '1.0.0',
                'deployment.environment' => $this->config->get('app_env', 'dev'),
            ])),
        );

        // Build TracerProvider
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor($processor)
            ->setResource($resource)
            ->setSampler(new AlwaysOnSampler())
            ->build();

        return new OTelTracer($tracerProvider, $serviceName);
    }
}
