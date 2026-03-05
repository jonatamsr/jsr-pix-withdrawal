<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Exception\Handler;

use App\Domain\Exception\InsufficientBalanceException;
use App\Exception\Handler\BusinessExceptionHandler;
use Hyperf\HttpMessage\Base\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class BusinessExceptionHandlerTest extends TestCase
{
    private BusinessExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new BusinessExceptionHandler();
    }

    // -- isValid --

    #[Test]
    public function isValidReturnsTrueForBusinessException(): void
    {
        $this->assertTrue($this->handler->isValid(new InsufficientBalanceException()));
    }

    #[Test]
    public function isValidReturnsFalseForNonBusinessException(): void
    {
        $this->assertFalse($this->handler->isValid(new RuntimeException('generic error')));
    }

    // -- handle --

    #[Test]
    public function handleDelegatesCodeMessageAndStatusFromException(): void
    {
        $exception = new InsufficientBalanceException();
        $response = new Response();

        $result = $this->handler->handle($exception, $response);
        $body = json_decode((string) $result->getBody(), true);

        $this->assertSame($exception->getErrorCode(), $body['code']);
        $this->assertSame($exception->getMessage(), $body['message']);
        $this->assertSame($exception->getHttpStatusCode(), $result->getStatusCode());
        $this->assertNull($body['details']);
    }

    #[Test]
    public function handleSetsContentTypeToJson(): void
    {
        $exception = new InsufficientBalanceException();
        $response = new Response();

        $result = $this->handler->handle($exception, $response);

        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }
}
