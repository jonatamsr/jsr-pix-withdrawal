<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Log;

use App\Infrastructure\Log\LoggerFactory;
use Hyperf\Logger\LoggerFactory as HyperfLoggerFactory;
use HyperfTest\Support\UsesMockery;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class LoggerFactoryTest extends TestCase
{
    use UsesMockery;

    #[Test]
    public function invokeFetchesDefaultLoggerFromContainer(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);

        $hyperfLoggerFactory = Mockery::mock(HyperfLoggerFactory::class);
        $hyperfLoggerFactory
            ->shouldReceive('get')
            ->once()
            ->with('default')
            ->andReturn($logger);

        $container = Mockery::mock(ContainerInterface::class);
        $container
            ->shouldReceive('get')
            ->once()
            ->with(HyperfLoggerFactory::class)
            ->andReturn($hyperfLoggerFactory);

        $factory = new LoggerFactory();
        $result = $factory($container);

        $this->assertSame($logger, $result);
    }
}
