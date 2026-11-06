<?php

declare(strict_types=1);

namespace App\Domain\Loan;

/** El estado se calcula, nunca se almacena (Artículo VII). */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
