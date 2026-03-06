<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Domain\Exception\RateLimitException;
use App\Domain\Port\RateLimiterInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterInterface $rateLimiter,
        private readonly ConfigInterface $config,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $dispatched = $request->getAttribute(Dispatched::class);

        if (! $dispatched instanceof Dispatched || ! $dispatched->isFound()) {
            return $handler->handle($request);
        }

        $accountId = $dispatched->params['accountId'] ?? null;

        if ($accountId === null) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $bucketKey = 'rate_limit:' . $accountId . ':' . $path;

        $create = (int) $this->config->get('rate_limit.create', 10);
        $consume = (int) $this->config->get('rate_limit.consume', 1);
        $capacity = (int) $this->config->get('rate_limit.capacity', 20);

        if (! $this->rateLimiter->attempt($bucketKey, $create, $capacity, $consume)) {
            throw new RateLimitException();
        }

        return $handler->handle($request);
    }
}
