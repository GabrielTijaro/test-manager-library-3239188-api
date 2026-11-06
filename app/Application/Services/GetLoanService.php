<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Support\LoanPresenter;

/** HU-08 · Ver un préstamo con su estado calculado. */
final class GetLoanService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly LoanRepository $loans,
    ) {
    }

    public function execute(string $id): array
    {
        $loan = $this->loans->find($id) ?? throw ApiException::notFound('El préstamo');

        return LoanPresenter::loan($loan, $this->clock->now());
    }
}
