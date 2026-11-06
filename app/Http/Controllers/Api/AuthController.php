<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\LoginService;
use App\Http\Support\ValidatesInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    use ValidatesInput;

    public function login(Request $request, LoginService $service): JsonResponse
    {
        $data = $this->validated($request->all(), [
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        return response()->json($service->execute($data['username'], $data['password']));
    }
}
