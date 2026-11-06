<?php

declare(strict_types=1);

namespace App\Application\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Iso
{
    /** ISO 8601 en UTC con «Z» (convención del contrato). */
    public static function format(?DateTimeImmutable $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
