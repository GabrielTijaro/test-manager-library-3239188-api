<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use App\Domain\Book\Book;
use App\Domain\Exceptions\ItemAlreadyReturnedException;
use App\Domain\Exceptions\LoanRuleException;
use DateTimeImmutable;
use DomainException;


final class Loan
{
    private array $items = [];

    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
    ) {
    }

    
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

        $book->withdraw(); 
        $this->items[] = new LoanItem(
            $itemId,
            $book->id,
            $book->title(),
            $book->isbn->value,
            $categoryName,
            LoanPolicy::dueAt($this->loanedAt),
        );
    }

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

    public function status(DateTimeImmutable $now): LoanStatus
    {
        return LoanPolicy::statusOf(array_map(static fn (LoanItem $i): LoanLine => $i->toLine(), $this->items), $now);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function unreturnedCount(): int
    {
        return count(array_filter($this->items, static fn (LoanItem $i): bool => !$i->isReturned()));
    }
}
