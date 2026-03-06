<?php

declare(strict_types=1);

namespace App\Domain\Strategy;

interface WithdrawMethodStrategyInterface
{
    public function validateAndBuild(array $data): WithdrawMethodData;
}
