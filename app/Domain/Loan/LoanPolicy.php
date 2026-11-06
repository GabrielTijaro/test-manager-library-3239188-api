<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use DateTimeImmutable;

/** Reglas RN-2 y RN-3 del spec, sin dependencias externas. */
final class LoanPolicy
{
    public const LOAN_DAYS = 14;
    public const MAX_LINES_PER_LOAN = 3;
    public const MAX_UNRETURNED_PER_MEMBER = 3;

    public static function dueAt(DateTimeImmutable $loanedAt): DateTimeImmutable
    {
        return $loanedAt->modify('+' . self::LOAN_DAYS . ' days');
    }

    /** @param list<LoanLine> $lines */
    public static function statusOf(array $lines, DateTimeImmutable $now): LoanStatus
    {
        $unreturned = array_values(array_filter($lines, static fn (LoanLine $l): bool => !$l->isReturned()));

        if ($unreturned === []) {
            return LoanStatus::Closed;
        }

        foreach ($unreturned as $line) {
            if ($line->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
        }

        return LoanStatus::Open;
    }

    /** Días completos transcurridos desde el vencimiento (decisión 8). */
    public static function daysLate(DateTimeImmutable $dueAt, DateTimeImmutable $now): int
    {
        $seconds = $now->getTimestamp() - $dueAt->getTimestamp();

        return $seconds <= 0 ? 0 : intdiv($seconds, 86400);
    }
}
