<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\CategoryRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DbCategoryRepository implements CategoryRepository
{
    public function all(): array
    {
        return DB::table('category')->orderBy('name')->get(['id', 'name'])
            ->map(static fn (object $r): array => ['id' => $r->id, 'name' => $r->name])
            ->all();
    }

    public function exists(string $id): bool
    {
        return Str::isUuid($id) && DB::table('category')->where('id', $id)->exists();
    }
}
