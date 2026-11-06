<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Support\Iso;
use App\Application\Support\Page;
use App\Domain\Loan\LoanPolicy;

/** HU-09 · Líneas vencidas, la más atrasada primero. */
final class ListOverdueService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly LoanRepository $loans,
    ) {
    }

    public function execute(?int $page, ?int $size): array
    {
        [$page, $size] = Page::normalize($page, $size);
        $now = $this->clock->now();
        $result = $this->loans->overdue($now, $page, $size);

        $items = array_map(static fn (array $r): array => [
            'loan_id' => $r['loan_id'],
            'item_id' => $r['item_id'],
            'book_title' => $r['book_title'],
            'member_name' => $r['member_name'],
            'due_at' => Iso::format($r['due_at']),
            'days_late' => LoanPolicy::daysLate($r['due_at'], $now),
        ], $result['items']);

        return Page::make($items, $result['total'], $page, $size);
    }
}
