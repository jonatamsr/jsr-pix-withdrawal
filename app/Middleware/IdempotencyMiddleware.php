<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\HttpMessage\Server\Response;
use Hyperf\Redis\Redis;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class IdempotencyMiddleware implements MiddlewareInterface
{
    private const string CACHE_PREFIX = 'idempotency:';

    private const int TTL_SECONDS = 86400; // 24 hours

    public function __construct(
        private readonly Redis $redis,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $handler->handle($request);
        }

        $idempotencyKey = $request->getHeaderLine('X-Idempotency-Key');

        if ($idempotencyKey === '') {
            return $handler->handle($request);
        }

        $cacheKey = self::CACHE_PREFIX . $idempotencyKey;

        $cached = $this->redis->get($cacheKey);

        if ($cached !== false) {
            /** @var array{status: int, headers: array<string, string[]>, body: string} $data */
            $data = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);

            $response = new Response();
            $response = $response->withStatus($data['status']);
            $response = $response->withContent($data['body']);

            foreach ($data['headers'] as $name => $values) {
                foreach ($values as $value) {
                    $response = $response->withAddedHeader($name, $value);
                }
            }

            return $response;
        }

        $response = $handler->handle($request);

        $data = [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
        ];

        $this->redis->set($cacheKey, json_encode($data, JSON_THROW_ON_ERROR), ['EX' => self::TTL_SECONDS]);

        return $response;
    }
}
