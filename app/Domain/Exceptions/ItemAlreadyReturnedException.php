<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

/** La línea ya fue devuelta; nunca se suma dos veces (CA-07.2). */
final class ItemAlreadyReturnedException extends DomainException
{
}
