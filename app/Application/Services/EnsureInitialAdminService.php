<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\ValueObjects\Username;
use RuntimeException;

/** T-17 · El admin inicial sale del entorno; nadie lo otorga en ejecución (decisión D-10). */
final class EnsureInitialAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
    ) {
    }

    /** @return bool true si lo creó, false si ya existía */
    public function execute(string $username, string $password): bool
    {
        $name = Username::normalize($username);
        if ($name === '' || $password === '') {
            throw new RuntimeException('ADMIN_USERNAME y ADMIN_PASSWORD son obligatorios.');
        }

        if ($this->users->findByUsername($name) !== null) {
            return false;
        }

        $this->users->insert($this->ids->uuid(), $name, $this->hasher->hash($password), Role::Admin->value);

        return true;
    }
}
