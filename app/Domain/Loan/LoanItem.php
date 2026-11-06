<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use DateTimeImmutable;

/** Línea de préstamo con título, ISBN y categoría congelados al prestar (RN-4). */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        public readonly ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function returnedAt(DateTimeImmutable $at): self
    {
        return new self($this->id, $this->bookId, $this->bookTitle, $this->isbn, $this->categoryName, $this->dueAt, $at);
    }

    public function toLine(): LoanLine
    {
        return new LoanLine($this->dueAt, $this->returnedAt);
    }
}
