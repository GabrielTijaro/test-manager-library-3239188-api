<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Support\Page;

/** HU-04 · Buscar libros. */
final class SearchBooksService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function execute(?string $q, ?string $categoryId, ?int $page, ?int $size): array
    {
        [$page, $size] = Page::normalize($page, $size);
        $result = $this->books->search($q !== null ? trim($q) : null, $categoryId, $page, $size);

        return Page::make($result['items'], $result['total'], $page, $size);
    }
}
