<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Ports\PasswordHasher;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Argon2id por defecto (D-09); LIBRARY_HASH_DRIVER permite bcrypt si el PHP no lo trae. */
final class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly string $driver)
    {
    }

    public function hash(string $plain): string
    {
        return Hash::driver($this->driver)->make($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        try {
            return Hash::driver($this->driver)->check($plain, $hash);
        } catch (RuntimeException) {
            return false;
        }
    }
}
