<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

class RequestIdMiddleware implements MiddlewareInterface
{
    private const string HEADER = 'X-Request-Id';

    private const string CONTEXT_KEY = 'request_id';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine(self::HEADER);

        if ($requestId === '') {
            $requestId = Uuid::uuid4()->toString();
        }

        Context::set(self::CONTEXT_KEY, $requestId);

        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER, $requestId);
    }
}
