<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Exception\Handler;

use App\Exception\Handler\ValidationExceptionHandler;
use Hyperf\Contract\MessageBag;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\HttpMessage\Base\Response;
use Hyperf\Validation\ValidationException;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class ValidationExceptionHandlerTest extends TestCase
{
    use UsesMockery;

    private ValidationExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ValidationExceptionHandler();
    }

    // -- isValid --

    #[Test]
    public function isValidReturnsTrueForValidationException(): void
    {
        $validator = Mockery::mock(ValidatorInterface::class);
        $exception = new ValidationException($validator);

        $this->assertTrue($this->handler->isValid($exception));
    }

    #[Test]
    public function isValidReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->handler->isValid(new RuntimeException('some error')));
    }

    // -- handle --

    #[Test]
    public function handleReturns422StatusCode(): void
    {
        $exception = $this->createValidationException(['amount' => ['The amount field is required.']]);
        $response = new Response();

        $result = $this->handler->handle($exception, $response);

        $this->assertSame(422, $result->getStatusCode());
    }

    #[Test]
    public function handleReturnsJsonContentType(): void
    {
        $exception = $this->createValidationException(['amount' => ['The amount field is required.']]);
        $response = new Response();

        $result = $this->handler->handle($exception, $response);

        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function handleReturnsValidationErrorCode(): void
    {
        $exception = $this->createValidationException(['method' => ['The method field is required.']]);
        $response = new Response();

        $result = $this->handler->handle($exception, $response);
        $body = json_decode((string) $result->getBody(), true);

        $this->assertSame('VALIDATION_ERROR', $body['code']);
    }

    #[Test]
    public function handleReturnsErrorDetails(): void
    {
        $errors = [
            'method' => ['The method field is required.'],
            'amount' => ['The amount must be at least 0.01.'],
        ];
        $exception = $this->createValidationException($errors);
        $response = new Response();

        $result = $this->handler->handle($exception, $response);
        $body = json_decode((string) $result->getBody(), true);

        $this->assertSame($errors, $body['details']);
    }

    #[Test]
    public function handleReturnsExceptionMessage(): void
    {
        $exception = $this->createValidationException(['pix.key' => ['The pix.key must be a valid email.']]);
        $response = new Response();

        $result = $this->handler->handle($exception, $response);
        $body = json_decode((string) $result->getBody(), true);

        $this->assertSame('The given data was invalid.', $body['message']);
    }

    private function createValidationException(array $errorMessages): ValidationException
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('getMessages')->andReturn($errorMessages);

        /** @var MockInterface|ValidatorInterface $validator */
        $validator = Mockery::mock(ValidatorInterface::class);
        $validator->shouldReceive('errors')->andReturn($messageBag);

        return new ValidationException($validator);
    }
}
