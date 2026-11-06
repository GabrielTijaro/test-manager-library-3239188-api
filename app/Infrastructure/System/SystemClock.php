<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        // Instante UTC truncado a segundos: coincide con timestamptz(0).
        return (new DateTimeImmutable('@' . time()))->setTimezone(new DateTimeZone('UTC'));
    }
}
