<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\BookRepository;

final class GetBookService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function execute(string $id): array
    {
        return $this->books->find($id) ?? throw ApiException::notFound('El libro');
    }
}
