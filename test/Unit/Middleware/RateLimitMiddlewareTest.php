<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Middleware;

use App\Domain\Exception\RateLimitException;
use App\Domain\Port\RateLimiterInterface;
use App\Middleware\RateLimitMiddleware;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\Handler;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
class RateLimitMiddlewareTest extends TestCase
{
    use UsesMockery;

    private MockInterface|RateLimiterInterface $rateLimiter;

    private ConfigInterface|MockInterface $config;

    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        $this->rateLimiter = Mockery::mock(RateLimiterInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);

        $this->middleware = new RateLimitMiddleware(
            $this->rateLimiter,
            $this->config,
        );
    }

    // -- passes through when no Dispatched attribute --

    #[Test]
    public function passesRequestWhenNoDispatchedAttribute(): void
    {
        $request = $this->mockRequest(dispatched: null);
        $handler = $this->mockHandler();
        $expectedResponse = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $result);
    }

    // -- passes through when route has no accountId --

    #[Test]
    public function passesRequestWhenRouteHasNoAccountId(): void
    {
        $dispatched = $this->mockDispatched(params: [], found: true);
        $request = $this->mockRequest(dispatched: $dispatched, path: '/health');
        $handler = $this->mockHandler();
        $expectedResponse = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $result);
    }

    // -- allows request when rate limiter allows --

    #[Test]
    public function allowsRequestWhenRateLimiterAllows(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';
        $path = '/account/' . $accountId . '/balance/withdraw';
        $expectedKey = 'rate_limit:' . $accountId . ':' . $path;

        $dispatched = $this->mockDispatched(params: ['accountId' => $accountId], found: true);
        $request = $this->mockRequest(dispatched: $dispatched, path: $path);
        $handler = $this->mockHandler();
        $expectedResponse = Mockery::mock(ResponseInterface::class);

        $this->mockConfig();

        $this->rateLimiter->shouldReceive('attempt')
            ->once()
            ->with($expectedKey, 10, 20, 1)
            ->andReturn(true);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $result);
    }

    // -- throws RateLimitException when rate limiter denies --

    #[Test]
    public function throwsRateLimitExceptionWhenRateLimiterDenies(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';
        $path = '/account/' . $accountId . '/balance/withdraw';

        $dispatched = $this->mockDispatched(params: ['accountId' => $accountId], found: true);
        $request = $this->mockRequest(dispatched: $dispatched, path: $path);
        $handler = $this->mockHandler();

        $this->mockConfig();

        $this->rateLimiter->shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        $this->expectException(RateLimitException::class);

        $this->middleware->process($request, $handler);
    }

    // -- uses accountId + path as bucket key --

    #[Test]
    public function usesCombinedAccountIdAndPathAsBucketKey(): void
    {
        $accountId = 'abc-123';
        $path = '/account/abc-123/balance/withdraw';
        $expectedKey = 'rate_limit:abc-123:/account/abc-123/balance/withdraw';

        $dispatched = $this->mockDispatched(params: ['accountId' => $accountId], found: true);
        $request = $this->mockRequest(dispatched: $dispatched, path: $path);
        $handler = $this->mockHandler();

        $this->mockConfig();

        $this->rateLimiter->shouldReceive('attempt')
            ->once()
            ->with($expectedKey, 10, 20, 1)
            ->andReturn(true);

        $handler->shouldReceive('handle')
            ->andReturn(Mockery::mock(ResponseInterface::class));

        $this->middleware->process($request, $handler);
    }

    // -- different accounts produce different keys --

    #[Test]
    public function differentAccountsProduceDifferentKeys(): void
    {
        $pathA = '/account/aaa/balance/withdraw';
        $pathB = '/account/bbb/balance/withdraw';

        $capturedKeys = [];

        $this->rateLimiter->shouldReceive('attempt')
            ->twice()
            ->withArgs(function (string $key) use (&$capturedKeys) {
                $capturedKeys[] = $key;
                return true;
            })
            ->andReturn(true);

        $this->mockConfig(times: 2);

        $handler = $this->mockHandler();
        $handler->shouldReceive('handle')
            ->andReturn(Mockery::mock(ResponseInterface::class));

        // Account A
        $dispatchedA = $this->mockDispatched(params: ['accountId' => 'aaa'], found: true);
        $requestA = $this->mockRequest(dispatched: $dispatchedA, path: $pathA);
        $this->middleware->process($requestA, $handler);

        // Account B
        $dispatchedB = $this->mockDispatched(params: ['accountId' => 'bbb'], found: true);
        $requestB = $this->mockRequest(dispatched: $dispatchedB, path: $pathB);
        $this->middleware->process($requestB, $handler);

        $this->assertCount(2, $capturedKeys);
        $this->assertNotSame($capturedKeys[0], $capturedKeys[1]);
        $this->assertStringContainsString('aaa', $capturedKeys[0]);
        $this->assertStringContainsString('bbb', $capturedKeys[1]);
    }

    // -- reads config values --

    #[Test]
    public function readsCreateAndCapacityFromConfig(): void
    {
        $accountId = 'test-account';
        $path = '/account/test-account/balance/withdraw';

        $dispatched = $this->mockDispatched(params: ['accountId' => $accountId], found: true);
        $request = $this->mockRequest(dispatched: $dispatched, path: $path);
        $handler = $this->mockHandler();

        $this->config->shouldReceive('get')->with('rate_limit.create', 10)->once()->andReturn(5);
        $this->config->shouldReceive('get')->with('rate_limit.consume', 1)->once()->andReturn(2);
        $this->config->shouldReceive('get')->with('rate_limit.capacity', 20)->once()->andReturn(15);

        $this->rateLimiter->shouldReceive('attempt')
            ->once()
            ->with(Mockery::any(), 5, 15, 2)
            ->andReturn(true);

        $handler->shouldReceive('handle')->andReturn(Mockery::mock(ResponseInterface::class));

        $this->middleware->process($request, $handler);
    }

    // -- does not call handler when rate limited --

    #[Test]
    public function doesNotCallHandlerWhenRateLimited(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';
        $path = '/account/' . $accountId . '/balance/withdraw';

        $dispatched = $this->mockDispatched(params: ['accountId' => $accountId], found: true);
        $request = $this->mockRequest(dispatched: $dispatched, path: $path);
        $handler = $this->mockHandler();

        $this->mockConfig();

        $this->rateLimiter->shouldReceive('attempt')->andReturn(false);

        $handler->shouldNotReceive('handle');

        try {
            $this->middleware->process($request, $handler);
        } catch (RateLimitException) {
            // expected
        }
    }

    // -- helpers --

    private function mockRequest(?Dispatched $dispatched, string $path = '/'): MockInterface|ServerRequestInterface
    {
        $uri = Mockery::mock(UriInterface::class);
        $uri->shouldReceive('getPath')->andReturn($path);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')
            ->with(Dispatched::class)
            ->andReturn($dispatched);
        $request->shouldReceive('getUri')->andReturn($uri);

        return $request;
    }

    private function mockHandler(): MockInterface|RequestHandlerInterface
    {
        return Mockery::mock(RequestHandlerInterface::class);
    }

    /**
     * @param array<string, string> $params
     */
    private function mockDispatched(array $params, bool $found): Dispatched|MockInterface
    {
        $dispatched = Mockery::mock(Dispatched::class);
        $dispatched->params = $params;
        $dispatched->shouldReceive('isFound')->andReturn($found);

        if ($found) {
            $handler = Mockery::mock(Handler::class);
            $handler->route = '/account/{accountId}/balance/withdraw';
            $dispatched->handler = $handler;
        }

        return $dispatched;
    }

    private function mockConfig(int $times = 1): void
    {
        $this->config->shouldReceive('get')->with('rate_limit.create', 10)->times($times)->andReturn(10);
        $this->config->shouldReceive('get')->with('rate_limit.consume', 1)->times($times)->andReturn(1);
        $this->config->shouldReceive('get')->with('rate_limit.capacity', 20)->times($times)->andReturn(20);
    }
}
