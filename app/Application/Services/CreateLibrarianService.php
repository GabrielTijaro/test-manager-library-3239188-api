<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Exceptions\DuplicateValueException;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\ValueObjects\Username;

/** HU-02 · Dar de alta bibliotecarios (admin). */
final class CreateLibrarianService
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
    ) {
    }

    public function execute(string $username, string $password, string $role): array
    {
        // CA-02.3: pedir el rol admin por la API responde 422.
        if ($role !== Role::Librarian->value) {
            throw ApiException::validation('role', 'solo se admite librarian', 'Solo se pueden crear usuarios con el rol librarian.');
        }

        $name = Username::normalize($username);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ApiException::validation('username', 'debe tener entre 1 y 120 caracteres');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw ApiException::validation('password', 'mínimo ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }

        $taken = new ApiException(409, 'USERNAME_TAKEN', 'Ese nombre de usuario ya existe.', [['field' => 'username', 'issue' => 'repetido']]);
        if ($this->users->findByUsername($name) !== null) {
            throw $taken;
        }

        $id = $this->ids->uuid();
        try {
            $this->users->insert($id, $name, $this->hasher->hash($password), Role::Librarian->value);
        } catch (DuplicateValueException) {
            throw $taken;
        }

        return ['id' => $id, 'username' => $name, 'role' => Role::Librarian->value];
    }
}
