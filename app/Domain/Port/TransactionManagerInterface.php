<?php

declare(strict_types=1);

namespace App\Domain\Port;

interface TransactionManagerInterface
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function execute(callable $callback): mixed;
}
