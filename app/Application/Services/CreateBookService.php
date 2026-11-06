<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Exceptions\DuplicateValueException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\IdGenerator;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use InvalidArgumentException;

/** HU-03 · Crear libro (admin). */
final class CreateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
        private readonly IdGenerator $ids,
    ) {
    }

    public function execute(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): array
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
            $isbnVo = Isbn::from($isbn);
        } catch (InvalidArgumentException $e) {
            throw ApiException::validation('isbn', 'debe tener 13 dígitos', $e->getMessage());
        }
        try {
            $quantity = Quantity::from($totalCopies);
        } catch (InvalidArgumentException $e) {
            throw ApiException::validation('total_copies', 'debe ser >= 1', $e->getMessage());
        }
        if (!$this->categories->exists($categoryId)) {
            throw ApiException::validation('category_id', 'no existe');
        }

        $id = $this->ids->uuid();
        try {
            $this->books->insert([
                'id' => $id,
                'title' => $title,
                'author' => $author,
                'isbn' => $isbnVo->value,
                'category_id' => $categoryId,
                'total_copies' => $quantity->value,
                'available_copies' => $quantity->value, // CA-03.1
            ]);
        } catch (DuplicateValueException) {
            throw new ApiException(409, 'ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.', [['field' => 'isbn', 'issue' => 'repetido']]);
        }

        return $this->books->find($id) ?? throw ApiException::notFound('El libro');
    }
}
