<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Exception\Handler;

use App\Exception\Handler\AppExceptionHandler;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Base\Response;
use HyperfTest\Support\UsesMockery;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class AppExceptionHandlerTest extends TestCase
{
    use UsesMockery;

    private AppExceptionHandler $handler;

    protected function setUp(): void
    {
        $logger = Mockery::mock(StdoutLoggerInterface::class)->shouldIgnoreMissing();
        $this->handler = new AppExceptionHandler($logger);
    }

    // -- isValid --

    #[Test]
    public function isValidAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->handler->isValid(new RuntimeException('anything')));
    }

    // -- handle --

    #[Test]
    public function handleReturnsGenericErrorBody(): void
    {
        $exception = new RuntimeException('something broke');
        $response = new Response();

        $result = $this->handler->handle($exception, $response);
        $body = json_decode((string) $result->getBody(), true);

        $this->assertSame('INTERNAL_ERROR', $body['code']);
        $this->assertSame('An unexpected error occurred', $body['message']);
        $this->assertSame(500, $result->getStatusCode());
        $this->assertNull($body['details']);
    }

    #[Test]
    public function handleDoesNotLeakExceptionDetails(): void
    {
        $exception = new RuntimeException('sensitive database error with credentials');
        $response = new Response();

        $result = $this->handler->handle($exception, $response);
        $body = (string) $result->getBody();

        $this->assertStringNotContainsString('sensitive', $body);
        $this->assertStringNotContainsString('credentials', $body);
    }

    #[Test]
    public function handleSetsContentTypeToJson(): void
    {
        $exception = new RuntimeException('error');
        $response = new Response();

        $result = $this->handler->handle($exception, $response);

        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function handleLogsExceptionDetails(): void
    {
        $capturedContext = [];

        $logger = Mockery::mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with('something broke', Mockery::capture($capturedContext));

        $handler = new AppExceptionHandler($logger);
        $exception = new RuntimeException('something broke');

        $handler->handle($exception, new Response());

        $this->assertSame(RuntimeException::class, $capturedContext['exception']);
        $this->assertSame(__FILE__, $capturedContext['file']);
        $this->assertSame($exception->getLine(), $capturedContext['line']);
        $this->assertStringContainsString('AppExceptionHandlerTest', $capturedContext['trace']);
    }
}
