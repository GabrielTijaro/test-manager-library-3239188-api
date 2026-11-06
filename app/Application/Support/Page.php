<?php

declare(strict_types=1);

namespace App\Application\Support;

final class Page
{
    public const DEFAULT_SIZE = 20;
    public const MAX_SIZE = 50;

    /** @return array{0:int,1:int} página y tamaño; size se recorta a 50 (decisión 3). */
    public static function normalize(?int $page, ?int $size): array
    {
        $page = max(1, $page ?? 1);
        $size = min(self::MAX_SIZE, max(1, $size ?? self::DEFAULT_SIZE));

        return [$page, $size];
    }

    /** @param list<array<string,mixed>> $items */
    public static function make(array $items, int $total, int $page, int $size): array
    {
        return ['items' => $items, 'page' => $page, 'size' => $size, 'total' => $total];
    }
}
