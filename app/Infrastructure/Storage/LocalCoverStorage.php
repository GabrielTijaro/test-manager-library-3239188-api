<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Ports\CoverStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Portadas en el disco local de Laravel; la clave es un UUID opaco, nunca una ruta. */
final class LocalCoverStorage implements CoverStorage
{
    public function store(string $bytes): string
    {
        $key = strtolower((string) Str::uuid());
        Storage::disk('local')->put($this->path($key), $bytes);

        return $key;
    }

    public function read(string $key): ?string
    {
        if (!Str::isUuid($key)) {
            return null;
        }
        $disk = Storage::disk('local');

        return $disk->exists($this->path($key)) ? $disk->get($this->path($key)) : null;
    }

    public function delete(string $key): void
    {
        if (Str::isUuid($key)) {
            Storage::disk('local')->delete($this->path($key));
        }
    }

    private function path(string $key): string
    {
        return 'covers/' . $key;
    }
}
