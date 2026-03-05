<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use Hyperf\Contract\StdoutLoggerInterface;
use Mockery;

trait MocksLogger
{
    protected function silentLogger(): StdoutLoggerInterface
    {
        /** @var StdoutLoggerInterface $logger */
        $logger = Mockery::mock(StdoutLoggerInterface::class)->shouldIgnoreMissing();

        return $logger;
    }
}
