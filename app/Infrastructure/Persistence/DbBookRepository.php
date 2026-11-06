<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exceptions\DuplicateValueException;
use App\Application\Ports\BookRepository;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DbBookRepository implements BookRepository
{
    public function search(?string $q, ?string $categoryId, int $page, int $size): array
    {
        $query = $this->activeWithCategory();

        if ($q !== null && $q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $query->where(static function (Builder $w) use ($like): void {
                $w->where('book.title', 'ilike', $like)->orWhere('book.author', 'ilike', $like);
            });
        }
        if ($categoryId !== null) {
            $query->where('book.category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('book.title')->orderBy('book.id')
            ->offset(($page - 1) * $size)->limit($size)->get();

        return [
            'items' => $rows->map(fn (object $r): array => $this->shape($r))->all(),
            'total' => $total,
        ];
    }

    public function find(string $id): ?array
    {
        if (!Str::isUuid($id)) {
            return null;
        }
        $row = $this->activeWithCategory()->where('book.id', $id)->first();

        return $row === null ? null : $this->shape($row);
    }

    public function lockActive(string $id): ?array
    {
        if (!Str::isUuid($id)) {
            return null;
        }
        $row = DB::table('book')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();

        return $row === null ? null : [
            'id' => $row->id,
            'total_copies' => (int) $row->total_copies,
            'available_copies' => (int) $row->available_copies,
            'cover_key' => $row->cover_key,
        ];
    }

    public function findManyActive(array $ids): array
    {
        $rows = $this->activeWithCategory()->whereIn('book.id', $ids)->get();
        $found = [];
        foreach ($rows as $r) {
            $found[$r->id] = [
                'id' => $r->id,
                'title' => $r->title,
                'isbn' => $r->isbn,
                'category_name' => $r->category_name,
            ];
        }

        return $found;
    }

    public function insert(array $book): void
    {
        try {
            DB::table('book')->insert($book + ['cover_key' => null, 'deleted_at' => null]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateValueException('isbn');
        }
    }

    public function update(string $id, array $fields): void
    {
        DB::table('book')->where('id', $id)->update($fields);
    }

    public function softDelete(string $id, DateTimeImmutable $at): void
    {
        DB::table('book')->where('id', $id)->update(['deleted_at' => Timestamps::toDb($at)]);
    }

    public function hasOpenLoans(string $id): bool
    {
        return DB::table('loan_item')->where('book_id', $id)->whereNull('returned_at')->exists();
    }

    public function decrementIfAvailable(string $id): bool
    {
        // Actualización condicional: la base decide quién se queda con el último ejemplar (ADR-002).
        return DB::table('book')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->where('available_copies', '>', 0)
            ->decrement('available_copies') === 1;
    }

    public function increment(string $id): void
    {
        DB::table('book')->where('id', $id)->increment('available_copies');
    }

    public function setCoverKey(string $id, ?string $key): void
    {
        DB::table('book')->where('id', $id)->update(['cover_key' => $key]);
    }

    private function activeWithCategory(): Builder
    {
        return DB::table('book')
            ->join('category', 'category.id', '=', 'book.category_id')
            ->whereNull('book.deleted_at')
            ->select(
                'book.id',
                'book.title',
                'book.author',
                'book.isbn',
                'book.total_copies',
                'book.available_copies',
                'book.cover_key',
                'category.id as category_id',
                'category.name as category_name',
            );
    }

    private function shape(object $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'author' => $r->author,
            'isbn' => $r->isbn,
            'category' => ['id' => $r->category_id, 'name' => $r->category_name],
            'total_copies' => (int) $r->total_copies,
            'available_copies' => (int) $r->available_copies,
            'cover_key' => $r->cover_key,
        ];
    }
}
