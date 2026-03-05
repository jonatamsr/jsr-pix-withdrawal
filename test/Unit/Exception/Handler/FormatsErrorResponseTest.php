<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Exception\Handler;

use App\Exception\Handler\FormatsErrorResponse;
use Hyperf\HttpMessage\Base\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
class FormatsErrorResponseTest extends TestCase
{
    #[Test]
    public function returnsJsonResponseWithCorrectStatus(): void
    {
        $handler = $this->createHandlerUsingTrait();
        $response = $this->createMockResponse();

        $result = $handler->callErrorResponse($response, 422, 'INSUFFICIENT_BALANCE', 'Insufficient balance');

        $body = json_decode((string) $result->getBody(), true);

        $this->assertSame(422, $result->getStatusCode());
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
        $this->assertSame('INSUFFICIENT_BALANCE', $body['code']);
        $this->assertSame('Insufficient balance', $body['message']);
        $this->assertNull($body['details']);
    }

    #[Test]
    public function includesDetailsWhenProvided(): void
    {
        $handler = $this->createHandlerUsingTrait();
        $response = $this->createMockResponse();
        $details = ['field' => 'amount', 'reason' => 'must be positive'];

        $result = $handler->callErrorResponse($response, 400, 'VALIDATION_ERROR', 'Invalid input', $details);

        $body = json_decode((string) $result->getBody(), true);
        $this->assertSame('amount', $body['details']['field']);
        $this->assertSame('must be positive', $body['details']['reason']);
    }

    private function createHandlerUsingTrait(): object
    {
        return new class {
            use FormatsErrorResponse {
                errorResponse as public callErrorResponse;
            }
        };
    }

    private function createMockResponse(): ResponseInterface
    {
        return new Response();
    }
}
