<?php

declare(strict_types=1);

use App\Infrastructure\Observability\OTel\OTelTracerFactory;

use function Hyperf\Support\env;

return [
    'default' => env('TRACER_DRIVER', 'otel'),

    'enable' => [
        'coroutine' => false,
        'db' => true,
        'exception' => true,
        'guzzle' => true,
        'method' => false,
        'redis' => true,
        'rpc' => false,
        'ignore_exceptions' => [],
    ],

    'tracer' => [
        'otel' => [
            'driver' => OTelTracerFactory::class,
            'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://jaeger:4318/v1/traces'),
            'content_type' => 'application/json',
            'service_name' => env('APP_NAME', 'jsr-pix-withdrawal'),
            'processor' => 'simple',
        ],
    ],

    'tags' => [
        'http_client' => [
            'http.url' => 'http.url',
            'http.method' => 'http.method',
            'http.status_code' => 'http.status_code',
        ],
        'redis' => [
            'arguments' => 'arguments',
            'result' => 'result',
        ],
        'db' => [
            'db.query' => 'db.query',
            'db.statement' => 'db.statement',
            'db.query_time' => 'db.query_time',
        ],
        'exception' => [
            'class' => 'exception.class',
            'code' => 'exception.code',
            'message' => 'exception.message',
            'stack_trace' => 'exception.stack_trace',
        ],
        'request' => [
            'path' => 'request.path',
            'method' => 'request.method',
            'header' => 'request.header',
            'uri' => 'request.uri',
        ],
        'coroutine' => [
            'id' => 'coroutine.id',
        ],
        'response' => [
            'status_code' => 'response.status_code',
        ],
        'rpc' => [
            'path' => 'rpc.path',
            'status' => 'rpc.status',
        ],
    ],
];
