<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Autorización por rol en cada endpoint (403 sin revelar si el recurso existe). */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!in_array($request->attributes->get('auth_role'), $roles, true)) {
            throw new ApiException(403, 'FORBIDDEN_ROLE', 'No tiene permisos para esta operación.');
        }

        return $next($request);
    }
}
