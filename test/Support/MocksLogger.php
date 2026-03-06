<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use Mockery;
use Mockery\LegacyMockInterface;
use Psr\Log\LoggerInterface;

trait MocksLogger
{
    protected function silentLogger(): LegacyMockInterface|LoggerInterface
    {
        return Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    }
}
