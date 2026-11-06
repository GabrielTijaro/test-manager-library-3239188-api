<?php

declare(strict_types=1);

namespace App\Application\Ports;

use DateTimeImmutable;

interface BookRepository
{
    /** @return array{items:list<array<string,mixed>>, total:int} solo libros activos, por título */
    public function search(?string $q, ?string $categoryId, int $page, int $size): array;

    /** Libro activo con forma de contrato, o null. */
    public function find(string $id): ?array;

    /** Fila del libro activo bloqueada para actualizar: id, total_copies, available_copies, cover_key. */
    public function lockActive(string $id): ?array;

    /**
     * @param list<string> $ids
     * @return array<string, array{id:string, title:string, isbn:string, category_name:string}>
     */
    public function findManyActive(array $ids): array;

    /**
     * @param array{id:string,title:string,author:string,isbn:string,category_id:string,total_copies:int,available_copies:int} $book
     * @throws \App\Application\Exceptions\DuplicateValueException
     */
    public function insert(array $book): void;

    /** @param array<string,mixed> $fields */
    public function update(string $id, array $fields): void;

    public function softDelete(string $id, DateTimeImmutable $at): void;

    public function hasOpenLoans(string $id): bool;

    /** Descuenta 1 disponible solo si queda alguno. true si lo logró (RNF-2). */
    public function decrementIfAvailable(string $id): bool;

    public function increment(string $id): void;

    public function setCoverKey(string $id, ?string $key): void;
}
