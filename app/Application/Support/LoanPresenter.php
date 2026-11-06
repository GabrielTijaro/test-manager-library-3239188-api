<?php

declare(strict_types=1);

namespace App\Application\Support;

use App\Domain\Loan\LoanLine;
use App\Domain\Loan\LoanPolicy;
use DateTimeImmutable;

/** Da forma de contrato a un préstamo; el estado se calcula aquí (CA-08.3). */
final class LoanPresenter
{
    /** @param array<string,mixed> $item */
    public static function item(array $item): array
    {
        return [
            'id' => $item['id'],
            'loan_id' => $item['loan_id'],
            'book_id' => $item['book_id'],
            'book_title' => $item['book_title'],
            'isbn' => $item['isbn'],
            'category_name' => $item['category_name'],
            'due_at' => Iso::format($item['due_at']),
            'returned_at' => Iso::format($item['returned_at']),
        ];
    }

    /** @param array<string,mixed> $loan */
    public static function loan(array $loan, DateTimeImmutable $now): array
    {
        $lines = array_map(
            static fn (array $i): LoanLine => new LoanLine($i['due_at'], $i['returned_at']),
            $loan['items'],
        );

        return [
            'id' => $loan['id'],
            'member_id' => $loan['member_id'],
            'loaned_by_user_id' => $loan['loaned_by_user_id'],
            'loaned_at' => Iso::format($loan['loaned_at']),
            'status' => LoanPolicy::statusOf($lines, $now)->value,
            'items' => array_map(static fn (array $i): array => self::item($i), $loan['items']),
        ];
    }
}
