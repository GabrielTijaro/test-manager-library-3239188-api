<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\GetLoanService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ListLoansService;
use App\Application\Services\ListOverdueService;
use App\Application\Services\ReturnItemService;
use App\Http\Support\ValidatesInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController
{
    use ValidatesInput;

    public function store(Request $request, LendBooksService $service): JsonResponse
    {
        $d = $this->validated($request->all(), [
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:3'],
            'items.*.book_id' => ['required', 'uuid'],
        ]);

        $bookIds = array_map(
            static fn (array $item): string => strtolower($item['book_id']),
            array_values($d['items']),
        );

        return response()->json($service->execute(
            (string) $request->attributes->get('auth_user_id'),
            strtolower($d['member_id']),
            $bookIds,
        ), 201);
    }

    public function index(Request $request, ListLoansService $service): JsonResponse
    {
        $q = $this->validated($request->query(), [
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,overdue,closed'],
            'member_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($service->execute(
            $q['from'] ?? null,
            $q['to'] ?? null,
            $q['status'] ?? null,
            isset($q['member_id']) ? strtolower($q['member_id']) : null,
            isset($q['page']) ? (int) $q['page'] : null,
            isset($q['size']) ? (int) $q['size'] : null,
        ));
    }

    public function show(string $id, GetLoanService $service): JsonResponse
    {
        return response()->json($service->execute(strtolower($id)));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return response()->json($service->execute(strtolower($loanId), strtolower($itemId)));
    }

    public function overdue(Request $request, ListOverdueService $service): JsonResponse
    {
        $q = $this->validated($request->query(), [
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($service->execute(
            isset($q['page']) ? (int) $q['page'] : null,
            isset($q['size']) ? (int) $q['size'] : null,
        ));
    }
}
