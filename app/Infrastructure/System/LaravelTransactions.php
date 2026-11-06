<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use App\Application\Ports\Transactions;
use Illuminate\Support\Facades\DB;

final class LaravelTransactions implements Transactions
{
    public function run(callable $work): mixed
    {
        return DB::transaction(static fn () => $work());
    }
}
