<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use App\Application\Ports\IdGenerator;
use Illuminate\Support\Str;

final class UuidGenerator implements IdGenerator
{
    public function uuid(): string
    {
        return strtolower((string) Str::uuid());
    }
}
