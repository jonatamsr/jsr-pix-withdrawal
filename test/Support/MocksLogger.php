<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use Hyperf\Contract\StdoutLoggerInterface;
use Mockery;
use Mockery\LegacyMockInterface;

trait MocksLogger
{
    protected function silentLogger(): LegacyMockInterface|StdoutLoggerInterface
    {
        return Mockery::mock(StdoutLoggerInterface::class)->shouldIgnoreMissing();
    }
}
