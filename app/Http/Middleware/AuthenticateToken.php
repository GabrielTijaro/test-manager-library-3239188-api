<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\TokenIssuer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** CA-01.3: todo endpoint salvo login y /health exige token (401 si falta o es inválido). */
final class AuthenticateToken
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m) !== 1) {
            throw new ApiException(401, 'UNAUTHENTICATED', 'Falta el token de autenticación.');
        }

        $claims = $this->tokens->verify($m[1]);
        if ($claims === null) {
            throw new ApiException(401, 'UNAUTHENTICATED', 'El token es inválido o venció.');
        }

        $request->attributes->set('auth_user_id', $claims['sub']);
        $request->attributes->set('auth_role', $claims['role']->value);

        return $next($request);
    }
}
