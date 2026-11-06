<?php

use App\Application\Exceptions\ApiException;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // RNF-4: todo error HTTP usa el mismo cuerpo { code, message, details }; nunca trazas ni SQL.
        $exceptions->shouldRenderJsonWhen(static fn (): bool => true);
        $exceptions->dontReport([ApiException::class]);

        $exceptions->render(static function (ApiException $e, Request $request) {
            return response()->json([
                'code' => $e->errorCode,
                'message' => $e->getMessage(),
                'details' => $e->details,
            ], $e->status);
        });

        $exceptions->render(static function (Throwable $e, Request $request) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            [$status, $code, $message] = match (true) {
                $status === 404 => [404, 'NOT_FOUND', 'El recurso no existe.'],
                $status === 405 => [405, 'METHOD_NOT_ALLOWED', 'Método no permitido.'],
                $status === 413 => [422, 'VALIDATION_ERROR', 'El archivo supera el tamaño permitido.'],
                $status >= 500 => [500, 'INTERNAL_ERROR', 'Ocurrió un error interno.'],
                default => [$status, 'REQUEST_ERROR', 'No se pudo procesar la solicitud.'],
            };

            return response()->json(['code' => $code, 'message' => $message, 'details' => []], $status);
        });
    })->create();
