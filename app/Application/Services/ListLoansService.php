<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Support\LoanPresenter;
use App\Application\Support\Page;
use App\Domain\Exceptions\InvalidDateRangeException;
use App\Domain\ValueObjects\DateRange;
use InvalidArgumentException;

/** HU-08 · Listar por rango de fechas, estado y socio; más recientes primero. */
final class ListLoansService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly LoanRepository $loans,
    ) {
    }

    public function execute(?string $from, ?string $to, ?string $status, ?string $memberId, ?int $page, ?int $size): array
    {
        try {
            $range = DateRange::of($from, $to);
        } catch (InvalidDateRangeException $e) {
            throw new ApiException(422, 'INVALID_DATE_RANGE', $e->getMessage(), [['field' => 'to', 'issue' => 'anterior a from']]);
        } catch (InvalidArgumentException $e) {
            throw ApiException::validation('from', 'fecha inválida', $e->getMessage());
        }

        [$page, $size] = Page::normalize($page, $size);
        $now = $this->clock->now();
        $result = $this->loans->list($range->start, $range->endExclusive, $status, $memberId, $now, $page, $size);

        $items = array_map(static fn (array $l): array => LoanPresenter::loan($l, $now), $result['items']);

        return Page::make($items, $result['total'], $page, $size);
    }
}
