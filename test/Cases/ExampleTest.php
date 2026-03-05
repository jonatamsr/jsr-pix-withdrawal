<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ExampleTest extends TestCase
{
    public function testExample()
    {
        $this->get('/')->assertOk()->assertSee('Hyperf');
    }
}
