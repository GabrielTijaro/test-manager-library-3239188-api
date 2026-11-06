<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\Transactions;
use App\Application\Support\LoanPresenter;

/** HU-07 · Devolver una línea. Nunca suma dos veces (CA-07.2). */
final class ReturnItemService
{
    public function __construct(
        private readonly Transactions $transactions,
        private readonly Clock $clock,
        private readonly LoanRepository $loans,
        private readonly BookRepository $books,
    ) {
    }

    public function execute(string $loanId, string $itemId): array
    {
        return $this->transactions->run(function () use ($loanId, $itemId): array {
            $item = $this->loans->findItem($loanId, $itemId) ?? throw ApiException::notFound('La línea del préstamo');
            $now = $this->clock->now();

            // Actualización condicional: si dos devoluciones coinciden, solo una afecta la fila.
            if (!$this->loans->markReturned($loanId, $itemId, $now)) {
                throw new ApiException(409, 'ITEM_ALREADY_RETURNED', 'Esa línea ya fue devuelta.');
            }
            $this->books->increment($item['book_id']);

            $item['returned_at'] = $now;

            return LoanPresenter::item($item);
        });
    }
}
