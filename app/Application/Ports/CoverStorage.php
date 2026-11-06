<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface CoverStorage
{
    /** Guarda el binario y devuelve una clave opaca (nunca una ruta). */
    public function store(string $bytes): string;

    public function read(string $key): ?string;

    public function delete(string $key): void;
}
