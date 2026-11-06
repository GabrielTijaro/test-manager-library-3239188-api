<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use finfo;

/** GET /books/{id}/cover: el binario o 404 (decisión 2: cualquier rol autenticado). */
final class GetCoverService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
    ) {
    }

    /** @return array{bytes:string, mime:string} */
    public function execute(string $bookId): array
    {
        $book = $this->books->find($bookId) ?? throw ApiException::notFound('El libro');
        $key = $book['cover_key'] ?? throw ApiException::notFound('La portada');
        $bytes = $this->storage->read($key) ?? throw ApiException::notFound('La portada');

        return ['bytes' => $bytes, 'mime' => (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream'];
    }
}
