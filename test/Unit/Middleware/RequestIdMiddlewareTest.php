<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Middleware;

use App\Middleware\RequestIdMiddleware;
use Hyperf\Context\Context;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

/**
 * @internal
 */
class RequestIdMiddlewareTest extends TestCase
{
    use UsesMockery;

    private RequestIdMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new RequestIdMiddleware();
    }

    protected function tearDown(): void
    {
        Context::destroy('request_id');
    }

    // -- generates UUID v4 when header is absent --

    #[Test]
    public function generatesUuidWhenHeaderIsAbsent(): void
    {
        $request = $this->mockRequest('');
        $handler = $this->mockHandler();
        $response = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($response);

        $response->shouldReceive('withHeader')
            ->once()
            ->withArgs(function (string $name, string $value) {
                $this->assertSame('X-Request-Id', $name);
                $this->assertTrue(Uuid::isValid($value), "Expected valid UUID v4, got: {$value}");

                return true;
            })
            ->andReturnSelf();

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($response, $result);
        $this->assertTrue(Uuid::isValid(Context::get('request_id')));
    }

    // -- propagates client-provided X-Request-Id --

    #[Test]
    public function propagatesClientProvidedRequestId(): void
    {
        $clientRequestId = 'client-provided-id-456';
        $request = $this->mockRequest($clientRequestId);
        $handler = $this->mockHandler();
        $response = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($response);

        $response->shouldReceive('withHeader')
            ->once()
            ->with('X-Request-Id', $clientRequestId)
            ->andReturnSelf();

        $result = $this->middleware->process($request, $handler);

        $this->assertSame($response, $result);
        $this->assertSame($clientRequestId, Context::get('request_id'));
    }

    // -- stores request ID in Context --

    #[Test]
    public function storesRequestIdInContext(): void
    {
        $request = $this->mockRequest('ctx-test-id');
        $handler = $this->mockHandler();
        $response = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')->andReturn($response);
        $response->shouldReceive('withHeader')->andReturnSelf();

        $this->middleware->process($request, $handler);

        $this->assertSame('ctx-test-id', Context::get('request_id'));
    }

    // -- adds X-Request-Id to response --

    #[Test]
    public function addsRequestIdHeaderToResponse(): void
    {
        $request = $this->mockRequest('resp-header-id');
        $handler = $this->mockHandler();
        $response = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')->andReturn($response);

        $response->shouldReceive('withHeader')
            ->once()
            ->with('X-Request-Id', 'resp-header-id')
            ->andReturnSelf();

        $this->middleware->process($request, $handler);
    }

    // -- generated IDs are unique per request --

    #[Test]
    public function generatesUniqueIdsForDifferentRequests(): void
    {
        $ids = [];

        for ($i = 0; $i < 3; ++$i) {
            $request = $this->mockRequest('');
            $handler = $this->mockHandler();
            $response = Mockery::mock(ResponseInterface::class);

            $handler->shouldReceive('handle')->andReturn($response);
            $response->shouldReceive('withHeader')
                ->withArgs(function (string $name, string $value) use (&$ids) {
                    $ids[] = $value;

                    return true;
                })
                ->andReturnSelf();

            $this->middleware->process($request, $handler);
        }

        $this->assertCount(3, array_unique($ids));
    }

    // -- handler is always called --

    #[Test]
    public function alwaysCallsHandler(): void
    {
        $request = $this->mockRequest('handler-test');
        $handler = $this->mockHandler();
        $response = Mockery::mock(ResponseInterface::class);

        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($response);

        $response->shouldReceive('withHeader')->andReturnSelf();

        $this->middleware->process($request, $handler);
    }

    // -- helpers --

    private function mockRequest(string $requestId): MockInterface|ServerRequestInterface
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('X-Request-Id')
            ->andReturn($requestId);

        return $request;
    }

    private function mockHandler(): MockInterface|RequestHandlerInterface
    {
        return Mockery::mock(RequestHandlerInterface::class);
    }
}
