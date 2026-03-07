<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Port\TransactionManagerInterface;
use Hyperf\DbConnection\Db;

class DbTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly Db $db)
    {
    }

    public function execute(callable $callback): mixed
    {
        return $this->db->transaction($callback);
    }
}
