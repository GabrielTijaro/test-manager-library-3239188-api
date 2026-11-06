<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;

/** Conversión entre instantes UTC del dominio y timestamptz de PostgreSQL. */
final class Timestamps
{
    public static function toDb(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }

    public static function fromDb(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }
}
