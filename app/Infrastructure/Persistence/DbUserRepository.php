<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exceptions\DuplicateValueException;
use App\Application\Ports\UserRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DbUserRepository implements UserRepository
{
    public function findByUsername(string $username): ?array
    {
        $row = DB::table('user')->where('username', $username)->first();

        return $row === null ? null : [
            'id' => $row->id,
            'username' => $row->username,
            'password_hash' => $row->password_hash,
            'role' => $row->role,
        ];
    }

    public function insert(string $id, string $username, string $passwordHash, string $role): void
    {
        try {
            DB::table('user')->insert([
                'id' => $id,
                'username' => $username,
                'password_hash' => $passwordHash,
                'role' => $role,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateValueException('username');
        }
    }
}
