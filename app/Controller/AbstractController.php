<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Contract\ResponseInterface;

abstract class AbstractController
{
    public function __construct(
        protected ResponseInterface $response
    ) {
    }
}
