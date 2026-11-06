<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\CreateLibrarianService;
use App\Http\Support\ValidatesInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController
{
    use ValidatesInput;

    public function store(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $data = $this->validated($request->all(), [
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:200'],
            'role' => ['required', 'string'],
        ]);

        return response()->json($service->execute($data['username'], $data['password'], $data['role']), 201);
    }
}
