<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\Transactions;

/** CA-03.5 · Baja lógica; un libro con préstamos abiertos no se da de baja. */
final class DeleteBookService
{
    public function __construct(
        private readonly Transactions $transactions,
        private readonly BookRepository $books,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $this->books->lockActive($id) ?? throw ApiException::notFound('El libro');

            if ($this->books->hasOpenLoans($id)) {
                throw new ApiException(409, 'BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos abiertos y no se puede dar de baja.');
            }

            $this->books->softDelete($id, $this->clock->now());
        });
    }
}
