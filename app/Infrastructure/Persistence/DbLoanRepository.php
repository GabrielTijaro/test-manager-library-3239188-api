<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\LoanRepository;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DbLoanRepository implements LoanRepository
{
    public function insert(array $loan, array $items): void
    {
        DB::table('loan')->insert([
            'id' => $loan['id'],
            'member_id' => $loan['member_id'],
            'loaned_by_user_id' => $loan['loaned_by_user_id'],
            'loaned_at' => Timestamps::toDb($loan['loaned_at']),
        ]);

        DB::table('loan_item')->insert(array_map(static fn (array $i): array => [
            'id' => $i['id'],
            'loan_id' => $loan['id'],
            'book_id' => $i['book_id'],
            'book_title' => $i['book_title'],
            'isbn' => $i['isbn'],
            'category_name' => $i['category_name'],
            'due_at' => Timestamps::toDb($i['due_at']),
            'returned_at' => null,
        ], $items));
    }

    public function find(string $id): ?array
    {
        if (!Str::isUuid($id)) {
            return null;
        }
        $row = DB::table('loan')->where('id', $id)->first();

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    public function list(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $toExclusive,
        ?string $status,
        ?string $memberId,
        DateTimeImmutable $now,
        int $page,
        int $size,
    ): array {
        $query = DB::table('loan');

        if ($from !== null) {
            $query->where('loaned_at', '>=', Timestamps::toDb($from));
        }
        if ($toExclusive !== null) {
            $query->where('loaned_at', '<', Timestamps::toDb($toExclusive));
        }
        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }

        // El estado se deriva de las líneas; no hay columna (Artículo VII).
        $nowDb = Timestamps::toDb($now);
        $unreturned = static fn (?string $overdueAt): Closure => static function (Builder $sub) use ($overdueAt): void {
            $sub->selectRaw('1')->from('loan_item')
                ->whereColumn('loan_item.loan_id', 'loan.id')
                ->whereNull('loan_item.returned_at');
            if ($overdueAt !== null) {
                $sub->where('loan_item.due_at', '<', $overdueAt);
            }
        };
        match ($status) {
            'closed' => $query->whereNotExists($unreturned(null)),
            'overdue' => $query->whereExists($unreturned($nowDb)),
            'open' => $query->whereExists($unreturned(null))->whereNotExists($unreturned($nowDb)),
            default => null,
        };

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('loaned_at')->orderBy('id')
            ->offset(($page - 1) * $size)->limit($size)->get()->all();

        return ['items' => $this->hydrate($rows), 'total' => $total];
    }

    public function findItem(string $loanId, string $itemId): ?array
    {
        if (!Str::isUuid($loanId) || !Str::isUuid($itemId)) {
            return null;
        }
        $row = DB::table('loan_item')->where('id', $itemId)->where('loan_id', $loanId)->first();

        return $row === null ? null : $this->item($row);
    }

    public function markReturned(string $loanId, string $itemId, DateTimeImmutable $at): bool
    {
        return DB::table('loan_item')
            ->where('id', $itemId)
            ->where('loan_id', $loanId)
            ->whereNull('returned_at')
            ->update(['returned_at' => Timestamps::toDb($at)]) === 1;
    }

    public function overdue(DateTimeImmutable $now, int $page, int $size): array
    {
        $query = DB::table('loan_item as li')
            ->join('loan as l', 'l.id', '=', 'li.loan_id')
            ->join('member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('li.returned_at')
            ->where('li.due_at', '<', Timestamps::toDb($now));

        $total = (clone $query)->count();
        $rows = $query
            ->select('li.id as item_id', 'li.loan_id', 'li.book_title', 'm.full_name as member_name', 'li.due_at')
            ->orderBy('li.due_at')->orderBy('li.id')
            ->offset(($page - 1) * $size)->limit($size)->get();

        return [
            'items' => $rows->map(static fn (object $r): array => [
                'loan_id' => $r->loan_id,
                'item_id' => $r->item_id,
                'book_title' => $r->book_title,
                'member_name' => $r->member_name,
                'due_at' => Timestamps::fromDb($r->due_at),
            ])->all(),
            'total' => $total,
        ];
    }

    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        // Solo valores congelados de loan_item: nada del catálogo vivo (Artículo VIII).
        return DB::table('loan_item as li')
            ->join('loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.loaned_at', '>=', Timestamps::toDb($from))
            ->where('l.loaned_at', '<', Timestamps::toDb($toExclusive))
            ->groupBy('li.book_id', 'li.book_title', 'li.category_name')
            ->select('li.book_id', 'li.book_title', 'li.category_name', DB::raw('count(*) as times_borrowed'))
            ->orderByDesc('times_borrowed')
            ->orderBy('li.book_title')
            ->get()
            ->map(static fn (object $r): array => [
                'book_id' => $r->book_id,
                'book_title' => $r->book_title,
                'category_name' => $r->category_name,
                'times_borrowed' => (int) $r->times_borrowed,
            ])->all();
    }

    public function unreturnedCount(string $memberId): int
    {
        return DB::table('loan_item as li')
            ->join('loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('li.returned_at')
            ->count();
    }

    public function overdueCount(string $memberId, DateTimeImmutable $now): int
    {
        return DB::table('loan_item as li')
            ->join('loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('li.returned_at')
            ->where('li.due_at', '<', Timestamps::toDb($now))
            ->count();
    }

    /** @param list<object> $rows */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (object $r): string => $r->id, $rows);
        $items = DB::table('loan_item')->whereIn('loan_id', $ids)
            ->orderBy('book_title')->orderBy('id')->get()->groupBy('loan_id');

        return array_map(fn (object $r): array => [
            'id' => $r->id,
            'member_id' => $r->member_id,
            'loaned_by_user_id' => $r->loaned_by_user_id,
            'loaned_at' => Timestamps::fromDb($r->loaned_at),
            'items' => $items->get($r->id, collect())->map(fn (object $i): array => $this->item($i))->values()->all(),
        ], $rows);
    }

    private function item(object $i): array
    {
        return [
            'id' => $i->id,
            'loan_id' => $i->loan_id,
            'book_id' => $i->book_id,
            'book_title' => $i->book_title,
            'isbn' => $i->isbn,
            'category_name' => $i->category_name,
            'due_at' => Timestamps::fromDb($i->due_at),
            'returned_at' => Timestamps::fromDb($i->returned_at),
        ];
    }
}
