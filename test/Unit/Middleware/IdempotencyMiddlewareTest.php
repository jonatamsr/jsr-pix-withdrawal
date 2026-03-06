<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Middleware;

use App\Middleware\IdempotencyMiddleware;
use Hyperf\Redis\Redis;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
class IdempotencyMiddlewareTest extends TestCase
{
    use UsesMockery;

    private MockInterface|Redis $redis;

    private IdempotencyMiddleware $middleware;

    protected function setUp(): void
    {
        $this->redis = Mockery::mock(Redis::class);
        $this->middleware = new IdempotencyMiddleware($this->redis);
    }

    // -- non-POST requests pass through --

    #[Test]
    public function passesGetRequestWithoutIdempotencyCheck(): void
    {
        $request = $this->mockRequest(method: 'GET');
        $handler = $this->mockHandler();
        $expectedResponse = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $this->redis->shouldNotReceive('get');
        $this->redis->shouldNotReceive('set');

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $result);
    }

    // -- POST without header passes through --

    #[Test]
    public function passesPostRequestWithoutIdempotencyHeader(): void
    {
        $request = $this->mockRequest(method: 'POST', idempotencyKey: '');
        $handler = $this->mockHandler();
        $expectedResponse = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $this->redis->shouldNotReceive('get');
        $this->redis->shouldNotReceive('set');

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $result);
    }

    // -- first POST with idempotency key processes and caches --

    #[Test]
    public function processesFirstRequestAndCachesResponse(): void
    {
        $idempotencyKey = 'unique-key-123';
        $cacheKey = 'idempotency:' . $idempotencyKey;
        $responseBody = '{"id":"abc"}';

        $request = $this->mockRequest(method: 'POST', idempotencyKey: $idempotencyKey);
        $handler = $this->mockHandler();
        $response = $this->mockResponse(status: 201, headers: ['Content-Type' => ['application/json']], body: $responseBody);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($response);

        $this->redis->shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn(false);

        $this->redis->shouldReceive('set')
            ->once()
            ->withArgs(function (string $key, string $value, array $options) use ($cacheKey) {
                $this->assertSame($cacheKey, $key);
                $this->assertSame(86400, $options['EX']);

                $data = json_decode($value, true);
                $this->assertSame(201, $data['status']);
                $this->assertSame('{"id":"abc"}', $data['body']);
                $this->assertSame(['application/json'], $data['headers']['Content-Type']);

                return true;
            })
            ->andReturn(true);

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    // -- second POST with same key returns cached response --

    #[Test]
    public function returnsCachedResponseForDuplicateRequest(): void
    {
        $idempotencyKey = 'unique-key-123';
        $cacheKey = 'idempotency:' . $idempotencyKey;

        $cached = json_encode([
            'status' => 201,
            'headers' => ['Content-Type' => ['application/json']],
            'body' => '{"id":"abc"}',
        ]);

        $request = $this->mockRequest(method: 'POST', idempotencyKey: $idempotencyKey);
        $handler = $this->mockHandler();

        $this->redis->shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn($cached);

        $handler->shouldNotReceive('handle');

        $result = $this->middleware->process($request, $handler);

        $this->assertSame(201, $result->getStatusCode());
        $this->assertSame('{"id":"abc"}', (string) $result->getBody());
        $this->assertSame(['application/json'], $result->getHeader('Content-Type'));
    }

    // -- handler not called on cached response --

    #[Test]
    public function doesNotCallHandlerWhenCachedResponseExists(): void
    {
        $cached = json_encode([
            'status' => 200,
            'headers' => [],
            'body' => '{}',
        ]);

        $request = $this->mockRequest(method: 'POST', idempotencyKey: 'any-key');
        $handler = $this->mockHandler();

        $this->redis->shouldReceive('get')
            ->once()
            ->andReturn($cached);

        $handler->shouldNotReceive('handle');

        $this->middleware->process($request, $handler);
    }

    // -- different keys produce different cache entries --

    #[Test]
    public function differentKeysAreCachedSeparately(): void
    {
        $capturedKeys = [];

        $this->redis->shouldReceive('get')
            ->twice()
            ->withArgs(function (string $key) use (&$capturedKeys) {
                $capturedKeys[] = $key;
                return true;
            })
            ->andReturn(false);

        $this->redis->shouldReceive('set')
            ->twice()
            ->andReturn(true);

        $handler = $this->mockHandler();
        $response = $this->mockResponse(status: 201, headers: [], body: '{}');
        $handler->shouldReceive('handle')->andReturn($response);

        $requestA = $this->mockRequest(method: 'POST', idempotencyKey: 'key-aaa');
        $this->middleware->process($requestA, $handler);

        $requestB = $this->mockRequest(method: 'POST', idempotencyKey: 'key-bbb');
        $this->middleware->process($requestB, $handler);

        $this->assertCount(2, $capturedKeys);
        $this->assertSame('idempotency:key-aaa', $capturedKeys[0]);
        $this->assertSame('idempotency:key-bbb', $capturedKeys[1]);
    }

    // -- TTL is 24 hours --

    #[Test]
    public function cachesResponseWith24HourTtl(): void
    {
        $request = $this->mockRequest(method: 'POST', idempotencyKey: 'ttl-test');
        $handler = $this->mockHandler();
        $response = $this->mockResponse(status: 200, headers: [], body: '{}');

        $handler->shouldReceive('handle')->andReturn($response);

        $this->redis->shouldReceive('get')->andReturn(false);

        $this->redis->shouldReceive('set')
            ->once()
            ->withArgs(function (string $key, string $value, array $options) {
                $this->assertSame(86400, $options['EX']);
                return true;
            })
            ->andReturn(true);

        $this->middleware->process($request, $handler);
    }

    // -- PUT request passes through --

    #[Test]
    public function passesPutRequestWithoutIdempotencyCheck(): void
    {
        $request = $this->mockRequest(method: 'PUT', idempotencyKey: 'some-key');
        $handler = $this->mockHandler();
        $expectedResponse = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $this->redis->shouldNotReceive('get');
        $this->redis->shouldNotReceive('set');

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $result);
    }

    // -- DELETE request passes through --

    #[Test]
    public function passesDeleteRequestWithoutIdempotencyCheck(): void
    {
        $request = $this->mockRequest(method: 'DELETE', idempotencyKey: 'some-key');
        $handler = $this->mockHandler();
        $expectedResponse = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $this->redis->shouldNotReceive('get');

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $result);
    }

    // -- helpers --

    private function mockRequest(string $method, string $idempotencyKey = ''): MockInterface|ServerRequestInterface
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn($method);
        $request->shouldReceive('getHeaderLine')
            ->with('X-Idempotency-Key')
            ->andReturn($idempotencyKey);

        return $request;
    }

    private function mockHandler(): MockInterface|RequestHandlerInterface
    {
        return Mockery::mock(RequestHandlerInterface::class);
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function mockResponse(int $status, array $headers, string $body): MockInterface|ResponseInterface
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('__toString')->andReturn($body);

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn($status);
        $response->shouldReceive('getHeaders')->andReturn($headers);
        $response->shouldReceive('getBody')->andReturn($stream);

        return $response;
    }
}
