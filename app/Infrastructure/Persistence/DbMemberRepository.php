<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exceptions\DuplicateValueException;
use App\Application\Ports\MemberRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DbMemberRepository implements MemberRepository
{
    public function search(?string $q, int $page, int $size): array
    {
        $query = DB::table('member');
        if ($q !== null && $q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $query->where(static function (Builder $w) use ($like): void {
                $w->where('full_name', 'ilike', $like)->orWhere('document_number', 'ilike', $like);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')
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
        $row = DB::table('member')->where('id', $id)->first();

        return $row === null ? null : $this->shape($row);
    }

    public function lock(string $id): bool
    {
        if (!Str::isUuid($id)) {
            return false;
        }

        return DB::table('member')->where('id', $id)->lockForUpdate()->first() !== null;
    }

    public function insert(array $member): void
    {
        try {
            DB::table('member')->insert($member);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateValueException('document_number');
        }
    }

    private function shape(object $r): array
    {
        return ['id' => $r->id, 'full_name' => $r->full_name, 'document_number' => $r->document_number];
    }
}
