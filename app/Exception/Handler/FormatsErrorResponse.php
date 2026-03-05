<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;

trait FormatsErrorResponse
{
    private function errorResponse(
        ResponseInterface $response,
        int $status,
        string $code,
        string $message,
        mixed $details = null
    ): ResponseInterface {
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream(json_encode([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ])));
    }
}
