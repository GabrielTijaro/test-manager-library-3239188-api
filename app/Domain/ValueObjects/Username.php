<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/** El nombre de usuario se guarda en minúsculas y recortado (CA-02.2). */
final class Username
{
    public static function normalize(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }
}
