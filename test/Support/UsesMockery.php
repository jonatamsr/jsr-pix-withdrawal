<?php

declare(strict_types=1);

namespace HyperfTest\Support;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

trait UsesMockery
{
    use MockeryPHPUnitIntegration;
}
