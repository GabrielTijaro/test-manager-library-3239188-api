<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

/** ISBN de exactamente 13 dígitos (CA-03.2). */
final class Isbn
{
    private function __construct(public readonly string $value)
    {
    }

    public static function from(string $raw): self
    {
        $value = trim($raw);
        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw new InvalidArgumentException('El ISBN debe tener exactamente 13 dígitos.');
        }

        return new self($value);
    }
}
