<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

/** No quedan ejemplares disponibles (CA-06.3). */
final class BookNotAvailableException extends DomainException
{
}
