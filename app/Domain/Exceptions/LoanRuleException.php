<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

/** Se rompió una regla de forma del préstamo: 1 a 3 líneas, sin libro repetido (CA-06.1, CA-06.2). */
final class LoanRuleException extends DomainException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
