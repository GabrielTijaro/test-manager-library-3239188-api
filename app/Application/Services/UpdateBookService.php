<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Transactions;
use App\Domain\ValueObjects\Quantity;
use InvalidArgumentException;

/** HU-03 · Reemplaza título, autor, categoría y total (CA-03.3). */
final class UpdateBookService
{
    public function __construct(
        private readonly Transactions $transactions,
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function execute(string $id, string $title, string $author, string $categoryId, int $totalCopies): array
    {
        $title = trim($title);
        $author = trim($author);
        if ($title === '') {
            throw ApiException::validation('title', 'requerido');
        }
        if ($author === '') {
            throw ApiException::validation('author', 'requerido');
        }
        try {
            $quantity = Quantity::from($totalCopies);
        } catch (InvalidArgumentException $e) {
            throw ApiException::validation('total_copies', 'debe ser >= 1', $e->getMessage());
        }
        if (!$this->categories->exists($categoryId)) {
            throw ApiException::validation('category_id', 'no existe');
        }

        $this->transactions->run(function () use ($id, $title, $author, $categoryId, $quantity): void {
            $row = $this->books->lockActive($id) ?? throw ApiException::notFound('El libro');

            // Los disponibles se ajustan en la misma diferencia y nunca bajan de 0.
            $available = (int) $row['available_copies'] + ($quantity->value - (int) $row['total_copies']);
            if ($available < 0) {
                throw ApiException::validation('total_copies', 'dejaría los disponibles por debajo de 0');
            }

            $this->books->update($id, [
                'title' => $title,
                'author' => $author,
                'category_id' => $categoryId,
                'total_copies' => $quantity->value,
                'available_copies' => $available,
            ]);
        });

        return $this->books->find($id) ?? throw ApiException::notFound('El libro');
    }
}
