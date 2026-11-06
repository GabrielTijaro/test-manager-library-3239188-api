<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\LoanRepository;
use App\Domain\Exceptions\InvalidDateRangeException;
use App\Domain\ValueObjects\DateRange;
use InvalidArgumentException;

/** HU-10 · Libros más prestados; lee los valores congelados (ADR-004). */
final class MostBorrowedReportService
{
    public function __construct(private readonly LoanRepository $loans)
    {
    }

    public function execute(string $from, string $to): array
    {
        try {
            $range = DateRange::of($from, $to);
        } catch (InvalidDateRangeException $e) {
            throw new ApiException(422, 'INVALID_DATE_RANGE', $e->getMessage(), [['field' => 'to', 'issue' => 'anterior a from']]);
        } catch (InvalidArgumentException $e) {
            throw ApiException::validation('from', 'fecha inválida', $e->getMessage());
        }

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $this->loans->mostBorrowed($range->start, $range->endExclusive),
        ];
    }
}
