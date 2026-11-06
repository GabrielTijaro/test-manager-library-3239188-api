<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use DateTimeImmutable;

final class LoanLine
{
    public function __construct(
        public readonly DateTimeImmutable $dueAt,
        public readonly ?DateTimeImmutable $returnedAt,
    ) {
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isReturned() && $this->dueAt < $now;
    }
}
