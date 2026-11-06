<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Role;

interface TokenIssuer
{
    /** @return array{token:string, expires_in:int} */
    public function issue(string $userId, Role $role): array;

    /** @return array{sub:string, role:Role}|null null si es inválido o venció */
    public function verify(string $token): ?array;
}
