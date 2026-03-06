<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Exception\Handler;

use App\Domain\Exception\RateLimitException;
use App\Exception\Handler\RateLimitExceptionHandler;
use Hyperf\HttpMessage\Base\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class RateLimitExceptionHandlerTest extends TestCase
{
    private RateLimitExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RateLimitExceptionHandler();
    }

    // -- isValid --

    #[Test]
    public function isValidReturnsTrueForRateLimitException(): void
    {
        $this->assertTrue($this->handler->isValid(new RateLimitException()));
    }

    #[Test]
    public function isValidReturnsFalseForNonRateLimitException(): void
    {
        $this->assertFalse($this->handler->isValid(new RuntimeException('generic error')));
    }

    // -- handle --

    #[Test]
    public function handleReturns429StatusCode(): void
    {
        $exception = new RateLimitException();
        $response = new Response();

        $result = $this->handler->handle($exception, $response);

        $this->assertSame(429, $result->getStatusCode());
    }

    #[Test]
    public function handleIncludesRetryAfterHeader(): void
    {
        $exception = new RateLimitException();
        $response = new Response();

        $result = $this->handler->handle($exception, $response);

        $this->assertTrue($result->hasHeader('Retry-After'));
        $this->assertSame('1', $result->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function handleReturnsCorrectErrorBody(): void
    {
        $exception = new RateLimitException();
        $response = new Response();

        $result = $this->handler->handle($exception, $response);
        $body = json_decode((string) $result->getBody(), true);

        $this->assertSame('RATE_LIMIT_EXCEEDED', $body['code']);
        $this->assertSame('Too many requests. Please try again later', $body['message']);
        $this->assertNull($body['details']);
    }

    #[Test]
    public function handleSetsContentTypeToJson(): void
    {
        $exception = new RateLimitException();
        $response = new Response();

        $result = $this->handler->handle($exception, $response);

        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }
}
