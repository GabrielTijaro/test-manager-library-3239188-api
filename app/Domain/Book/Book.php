<?php

declare(strict_types=1);

namespace App\Domain\Book;

use App\Domain\Exceptions\BookNotAvailableException;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;


final class Book
{
    private function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        public readonly Isbn $isbn,
        private string $categoryId,
        private int $totalCopies,
        private int $availableCopies,
        private ?string $coverKey,
        private ?DateTimeImmutable $deletedAt,
    ) {
    }

    public static function create(string $id, string $title, string $author, Isbn $isbn, string $categoryId, Quantity $total): self
    {
        $title = trim($title);
        $author = trim($author);
        if ($title === '' || $author === '') {
            throw new InvalidArgumentException('El título y el autor son obligatorios.');
        }

        return new self($id, $title, $author, $isbn, $categoryId, $total->value, $total->value, null, null);
    }

    public static function restore(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        int $total,
        int $available,
        ?string $coverKey,
        ?DateTimeImmutable $deletedAt,
    ): self {
        if ($total < 1 || $available < 0 || $available > $total) {
            throw new DomainException('El libro guardado viola 0 <= disponibles <= total.');
        }

        return new self($id, $title, $author, $isbn, $categoryId, $total, $available, $coverKey, $deletedAt);
    }

   
    public function withdraw(): void
    {
        if ($this->availableCopies <= 0) {
            throw new BookNotAvailableException(sprintf('No quedan ejemplares disponibles de «%s».', $this->title));
        }
        $this->availableCopies--;
    }

    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new DomainException('Los disponibles no pueden superar el total de ejemplares.');
        }
        $this->availableCopies++;
    }

    public function resize(Quantity $newTotal): void
    {
        $available = $this->availableCopies + ($newTotal->value - $this->totalCopies);
        if ($available < 0) {
            throw new InvalidArgumentException('El nuevo total dejaría los disponibles por debajo de 0.');
        }
        $this->totalCopies = $newTotal->value;
        $this->availableCopies = $available;
    }


    public function softDelete(DateTimeImmutable $at): void
    {
        $this->deletedAt ??= $at;
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    }


