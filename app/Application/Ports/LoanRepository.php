<?php

declare(strict_types=1);

namespace App\Application\Ports;

use DateTimeImmutable;

interface LoanRepository
{
    /**
     * @param array{id:string, member_id:string, loaned_by_user_id:string, loaned_at:DateTimeImmutable} $loan
     * @param list<array{id:string, book_id:string, book_title:string, isbn:string, category_name:string, due_at:DateTimeImmutable}> $items
     */
    public function insert(array $loan, array $items): void;

    /** Préstamo con sus líneas, o null. */
    public function find(string $id): ?array;

    /** @return array{items:list<array<string,mixed>>, total:int} */
    public function list(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $toExclusive,
        ?string $status,
        ?string $memberId,
        DateTimeImmutable $now,
        int $page,
        int $size,
    ): array;

    public function findItem(string $loanId, string $itemId): ?array;

    /** Pone returned_at solo si seguía nulo. false si ya estaba devuelta (CA-07.2). */
    public function markReturned(string $loanId, string $itemId, DateTimeImmutable $at): bool;

    /** @return array{items:list<array<string,mixed>>, total:int} más vencidas primero */
    public function overdue(DateTimeImmutable $now, int $page, int $size): array;

    /** @return list<array{book_id:string, book_title:string, category_name:string, times_borrowed:int}> */
    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array;

    public function unreturnedCount(string $memberId): int;

    public function overdueCount(string $memberId, DateTimeImmutable $now): int;
}
