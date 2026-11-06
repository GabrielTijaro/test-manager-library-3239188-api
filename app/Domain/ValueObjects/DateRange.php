<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidDateRangeException;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Rango de fechas con ambos extremos inclusivos (decisión 6 de spec-gaps).
 * start = from 00:00 UTC; endExclusive = (to + 1 día) 00:00 UTC.
 */
final class DateRange
{
    private function __construct(
        public readonly ?DateTimeImmutable $start,
        public readonly ?DateTimeImmutable $endExclusive,
    ) {
    }

    public static function of(?string $from, ?string $to): self
    {
        $start = $from === null ? null : self::day($from);
        $end = $to === null ? null : self::day($to);

        if ($start !== null && $end !== null && $end < $start) {
            throw new InvalidDateRangeException('La fecha final no puede ser anterior a la inicial.');
        }

        return new self($start, $end?->modify('+1 day'));
    }

    private static function day(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = $errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

        if ($date === false || $hasErrors) {
            throw new InvalidArgumentException('Fecha inválida: use el formato AAAA-MM-DD.');
        }

        return $date;
    }
}
