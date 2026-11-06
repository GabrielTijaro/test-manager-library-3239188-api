<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface MemberRepository
{
    /** @return array{items:list<array<string,mixed>>, total:int} */
    public function search(?string $q, int $page, int $size): array;

    /** @return array{id:string, full_name:string, document_number:string}|null */
    public function find(string $id): ?array;

    /** Bloquea la fila del socio dentro de la transacción. false si no existe. */
    public function lock(string $id): bool;

    /**
     * @param array{id:string, full_name:string, document_number:string} $member
     * @throws \App\Application\Exceptions\DuplicateValueException
     */
    public function insert(array $member): void;
}
