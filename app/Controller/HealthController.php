<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Throwable;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly Db $db,
        private readonly Redis $redis,
    ) {
    }

    public function check(): \Psr\Http\Message\ResponseInterface
    {
        $checks = [
            'mysql' => $this->checkMysql(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return $this->response->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
        ])->withStatus($healthy ? 200 : 503);
    }

    private function checkMysql(): string
    {
        try {
            $this->db->select('SELECT 1');

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function checkRedis(): string
    {
        try {
            $this->redis->ping();

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
