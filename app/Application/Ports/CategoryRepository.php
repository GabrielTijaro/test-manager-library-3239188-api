<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface CategoryRepository
{
    /** @return list<array{id:string, name:string}> ordenadas por nombre */
    public function all(): array;

    public function exists(string $id): bool;
}
