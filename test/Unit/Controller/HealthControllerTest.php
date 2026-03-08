<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Controller;

use App\Controller\HealthController;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Redis\Redis;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use RuntimeException;

/**
 * @internal
 */
class HealthControllerTest extends TestCase
{
    use UsesMockery;

    private Db|MockInterface $db;

    private MockInterface|Redis $redis;

    private MockInterface|ResponseInterface $response;

    private HealthController $controller;

    protected function setUp(): void
    {
        $this->db = Mockery::mock(Db::class);
        $this->redis = Mockery::mock(Redis::class);
        $this->response = Mockery::mock(ResponseInterface::class);

        $this->controller = new HealthController(
            $this->response,
            $this->db,
            $this->redis
        );
    }

    // -- all services healthy --

    #[Test]
    public function checkReturns200WhenAllServicesAreUp(): void
    {
        $this->db->shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        $this->redis->shouldReceive('ping')
            ->once()
            ->andReturn(true);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->with([
                'status' => 'healthy',
                'checks' => [
                    'mysql' => 'ok',
                    'redis' => 'ok',
                ],
            ])
            ->andReturn($psrResponse);

        $psrResponse->shouldReceive('withStatus')
            ->once()
            ->with(200)
            ->andReturn($psrResponse);

        $result = $this->controller->check();

        $this->assertSame($psrResponse, $result);
    }

    // -- mysql down --

    #[Test]
    public function checkReturns503WhenMysqlIsDown(): void
    {
        $this->db->shouldReceive('select')
            ->once()
            ->andThrow(new RuntimeException('Connection refused'));

        $this->redis->shouldReceive('ping')
            ->once()
            ->andReturn(true);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->with([
                'status' => 'unhealthy',
                'checks' => [
                    'mysql' => 'fail',
                    'redis' => 'ok',
                ],
            ])
            ->andReturn($psrResponse);

        $psrResponse->shouldReceive('withStatus')
            ->once()
            ->with(503)
            ->andReturn($psrResponse);

        $result = $this->controller->check();

        $this->assertSame($psrResponse, $result);
    }

    // -- redis down --

    #[Test]
    public function checkReturns503WhenRedisIsDown(): void
    {
        $this->db->shouldReceive('select')
            ->once()
            ->andReturn([]);

        $this->redis->shouldReceive('ping')
            ->once()
            ->andThrow(new RuntimeException('Connection refused'));

        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->with([
                'status' => 'unhealthy',
                'checks' => [
                    'mysql' => 'ok',
                    'redis' => 'fail',
                ],
            ])
            ->andReturn($psrResponse);

        $psrResponse->shouldReceive('withStatus')
            ->once()
            ->with(503)
            ->andReturn($psrResponse);

        $result = $this->controller->check();

        $this->assertSame($psrResponse, $result);
    }

    // -- both services down --

    #[Test]
    public function checkReturns503WhenBothServicesAreDown(): void
    {
        $this->db->shouldReceive('select')
            ->once()
            ->andThrow(new RuntimeException('MySQL gone'));

        $this->redis->shouldReceive('ping')
            ->once()
            ->andThrow(new RuntimeException('Redis gone'));

        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->with([
                'status' => 'unhealthy',
                'checks' => [
                    'mysql' => 'fail',
                    'redis' => 'fail',
                ],
            ])
            ->andReturn($psrResponse);

        $psrResponse->shouldReceive('withStatus')
            ->once()
            ->with(503)
            ->andReturn($psrResponse);

        $result = $this->controller->check();

        $this->assertSame($psrResponse, $result);
    }
}
