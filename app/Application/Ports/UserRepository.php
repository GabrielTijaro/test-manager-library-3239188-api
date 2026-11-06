<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface UserRepository
{
    /** @return array{id:string, username:string, password_hash:string, role:string}|null */
    public function findByUsername(string $username): ?array;

    /** @throws \App\Application\Exceptions\DuplicateValueException */
    public function insert(string $id, string $username, string $passwordHash, string $role): void;
}
