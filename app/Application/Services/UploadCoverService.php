<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use finfo;

/** CA-03.4 · JPG/PNG de máximo 2 MB; el tipo se valida por contenido. */
final class UploadCoverService
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    private const ALLOWED = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
    ) {
    }

    public function execute(string $bookId, string $bytes): array
    {
        $book = $this->books->find($bookId) ?? throw ApiException::notFound('El libro');

        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw ApiException::validation('file', 'máximo 2 MB', 'La portada debe pesar como máximo 2 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, self::ALLOWED, true)) {
            throw ApiException::validation('file', 'solo JPG o PNG', 'La portada debe ser una imagen JPG o PNG.');
        }

        $newKey = $this->storage->store($bytes);
        try {
            $this->books->setCoverKey($bookId, $newKey);
        } catch (\Throwable $e) {
            $this->storage->delete($newKey);
            throw $e;
        }

        // El binario anterior se borra después de confirmar la nueva clave.
        if ($book['cover_key'] !== null) {
            $this->storage->delete($book['cover_key']);
        }

        return $this->books->find($bookId) ?? throw ApiException::notFound('El libro');
    }
}
