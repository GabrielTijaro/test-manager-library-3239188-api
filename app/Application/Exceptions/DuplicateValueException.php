<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

/** Lo lanzan los adaptadores cuando el motor rechaza un valor único repetido. */
final class DuplicateValueException extends RuntimeException
{
    public function __construct(public readonly string $field)
    {
        parent::__construct('Valor duplicado: ' . $field);
    }
}
