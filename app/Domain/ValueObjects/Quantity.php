<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

/** Cantidad de ejemplares: entero mayor o igual a 1 (CA-03.3). */
final class Quantity
{
    private function __construct(public readonly int $value)
    {
    }

    public static function from(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('El total de ejemplares debe ser mayor o igual a 1.');
        }

        return new self($value);
    }
}
