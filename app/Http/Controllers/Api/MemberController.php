<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\SearchMembersService;
use App\Http\Support\ValidatesInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController
{
    use ValidatesInput;

    public function index(Request $request, SearchMembersService $service): JsonResponse
    {
        $q = $this->validated($request->query(), [
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($service->execute(
            $q['q'] ?? null,
            isset($q['page']) ? (int) $q['page'] : null,
            isset($q['size']) ? (int) $q['size'] : null,
        ));
    }

    public function show(string $id, GetMemberService $service): JsonResponse
    {
        return response()->json($service->execute(strtolower($id)));
    }

    public function store(Request $request, CreateMemberService $service): JsonResponse
    {
        $d = $this->validated($request->all(), [
            'full_name' => ['required', 'string', 'max:200'],
            'document_number' => ['required', 'string', 'max:30'],
        ]);

        return response()->json($service->execute($d['full_name'], $d['document_number']), 201);
    }
}
