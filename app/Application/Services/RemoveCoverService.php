<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;

/** data-model §7: primero se anula cover_key; después se borra el binario. */
final class RemoveCoverService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
    ) {
    }

    public function execute(string $bookId): void
    {
        $book = $this->books->find($bookId) ?? throw ApiException::notFound('El libro');
        $key = $book['cover_key'];
        if ($key === null) {
            return;
        }

        $this->books->setCoverKey($bookId, null);
        $this->storage->delete($key);
    }
}
