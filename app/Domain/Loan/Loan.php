<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use App\Domain\Book\Book;
use App\Domain\Exceptions\ItemAlreadyReturnedException;
use App\Domain\Exceptions\LoanRuleException;
use DateTimeImmutable;
use DomainException;

/**
 * Agregado Loan (T-05). Un préstamo tiene de 1 a 3 líneas, sin libro repetido,
 * vence a 14 días y solo cambia por devolución (RN-2, RN-5).
 */
final class Loan
{
    /** @var list<LoanItem> */
    private array $items = [];

    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
    ) {
    }

    /**
     * Añade una línea: descontar el ejemplar y crear la línea son una sola operación.
     * Congela título, ISBN y categoría del instante del préstamo.
     */
    public function addItem(string $itemId, Book $book, string $categoryName): void
    {
        if (count($this->items) >= LoanPolicy::MAX_LINES_PER_LOAN) {
            throw new LoanRuleException('too_many_items', 'Un préstamo admite como máximo ' . LoanPolicy::MAX_LINES_PER_LOAN . ' libros.');
        }
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new LoanRuleException('duplicate_book', 'No se puede repetir el mismo libro en un préstamo.');
            }
        }

        $book->withdraw(); // lanza BookNotAvailableException; si falla, no se añade la línea

        $this->items[] = new LoanItem(
            $itemId,
            $book->id,
            $book->title(),
            $book->isbn->value,
            $categoryName,
            LoanPolicy::dueAt($this->loanedAt),
        );
    }

    /** Devuelve una línea y suma 1 al libro una sola vez (CA-07.1, CA-07.2). */
    public function returnItem(string $itemId, Book $book, DateTimeImmutable $at): void
    {
        foreach ($this->items as $i => $item) {
            if ($item->id !== $itemId) {
                continue;
            }
            if ($item->isReturned()) {
                throw new ItemAlreadyReturnedException('Esa línea ya fue devuelta.');
            }
            if ($item->bookId !== $book->id) {
                throw new DomainException('El libro no corresponde a la línea.');
            }

            $book->restock();
            $this->items[$i] = $item->returnedAt($at);

            return;
        }

        throw new DomainException('La línea no pertenece a este préstamo.');
    }

    /** Estado calculado; nunca se almacena (CA-08.3). */
    public function status(DateTimeImmutable $now): LoanStatus
    {
        return LoanPolicy::statusOf(array_map(static fn (LoanItem $i): LoanLine => $i->toLine(), $this->items), $now);
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function unreturnedCount(): int
    {
        return count(array_filter($this->items, static fn (LoanItem $i): bool => !$i->isReturned()));
    }
}
