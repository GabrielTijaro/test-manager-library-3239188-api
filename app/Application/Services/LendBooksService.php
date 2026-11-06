<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\Transactions;
use App\Application\Support\LoanPresenter;
use App\Domain\Loan\LoanPolicy;

/**
 * HU-06 · Registrar un préstamo.
 *
 * Orden de validación (decisión 5): forma 422 → vencidos 409 → límite 422 → sin ejemplares 409.
 * Descontar ejemplares y crear las líneas es una sola transacción (CA-06.3).
 */
final class LendBooksService
{
    public function __construct(
        private readonly Transactions $transactions,
        private readonly Clock $clock,
        private readonly IdGenerator $ids,
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly LoanRepository $loans,
    ) {
    }

    /** @param list<string> $bookIds en el orden pedido */
    public function execute(string $userId, string $memberId, array $bookIds): array
    {
        $count = count($bookIds);
        if ($count < 1 || $count > LoanPolicy::MAX_LINES_PER_LOAN) {
            throw ApiException::validation('items', 'debe tener entre 1 y ' . LoanPolicy::MAX_LINES_PER_LOAN . ' libros');
        }
        if ($count !== count(array_unique($bookIds))) {
            throw new ApiException(422, 'DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir el mismo libro en un préstamo.', [['field' => 'items', 'issue' => 'libro repetido']]);
        }

        return $this->transactions->run(function () use ($userId, $memberId, $bookIds, $count): array {
            // Bloquear al socio serializa sus préstamos y mantiene bien el conteo de 3.
            if (!$this->members->lock($memberId)) {
                throw ApiException::notFound('El socio');
            }
            $now = $this->clock->now();

            if ($this->loans->overdueCount($memberId, $now) > 0) {
                throw new ApiException(409, 'MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y no puede pedir otros hasta devolverlos.', [['field' => 'member_id', 'issue' => 'tiene libros vencidos']]);
            }

            $current = $this->loans->unreturnedCount($memberId);
            if ($current + $count > LoanPolicy::MAX_UNRETURNED_PER_MEMBER) {
                throw new ApiException(
                    422,
                    'MEMBER_LIMIT_EXCEEDED',
                    sprintf('El socio ya tiene %d libros sin devolver; el máximo es %d.', $current, LoanPolicy::MAX_UNRETURNED_PER_MEMBER),
                    [['field' => 'items', 'issue' => 'sin devolver: ' . $current]],
                );
            }

            $books = $this->books->findManyActive($bookIds);
            foreach ($bookIds as $i => $bookId) {
                if (!isset($books[$bookId])) {
                    throw new ApiException(404, 'NOT_FOUND', 'El libro no existe.', [['field' => "items[$i].book_id", 'issue' => 'no existe']]);
                }
            }

            // Orden estable para tomar los bloqueos y evitar interbloqueos entre préstamos.
            $ordered = $bookIds;
            sort($ordered);
            foreach ($ordered as $bookId) {
                if (!$this->books->decrementIfAvailable($bookId)) {
                    $i = (int) array_search($bookId, $bookIds, true);
                    throw new ApiException(
                        409,
                        'BOOK_NOT_AVAILABLE',
                        sprintf('No quedan ejemplares disponibles de «%s».', $books[$bookId]['title']),
                        [['field' => "items[$i].book_id", 'issue' => 'sin ejemplares']],
                    );
                }
            }

            $loanId = $this->ids->uuid();
            $dueAt = LoanPolicy::dueAt($now);
            $items = [];
            foreach ($bookIds as $bookId) {
                $items[] = [
                    'id' => $this->ids->uuid(),
                    'book_id' => $bookId,
                    // Valores congelados al instante del préstamo (RN-4).
                    'book_title' => $books[$bookId]['title'],
                    'isbn' => $books[$bookId]['isbn'],
                    'category_name' => $books[$bookId]['category_name'],
                    'due_at' => $dueAt,
                ];
            }
            $loan = ['id' => $loanId, 'member_id' => $memberId, 'loaned_by_user_id' => $userId, 'loaned_at' => $now];
            $this->loans->insert($loan, $items);

            $loan['items'] = array_map(
                static fn (array $i): array => $i + ['loan_id' => $loanId, 'returned_at' => null],
                $items,
            );

            return LoanPresenter::loan($loan, $now);
        });
    }
}
