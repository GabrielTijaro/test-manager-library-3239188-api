<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\ValueObjects\Username;

/** HU-01 · Iniciar sesión. */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    public function execute(string $username, string $password): array
    {
        $user = $this->users->findByUsername(Username::normalize($username));

        // CA-01.2: el mismo mensaje si falla el usuario o la clave.
        if ($user === null || !$this->hasher->verify($password, $user['password_hash'])) {
            throw new ApiException(401, 'INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
        }

        $role = Role::from($user['role']);
        $issued = $this->tokens->issue($user['id'], $role);

        return [
            'access_token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => $issued['expires_in'],
            'role' => $role->value,
        ];
    }
}
