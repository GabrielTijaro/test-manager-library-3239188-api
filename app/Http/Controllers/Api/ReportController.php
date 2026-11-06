<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\MostBorrowedReportService;
use App\Http\Support\ValidatesInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController
{
    use ValidatesInput;

    public function mostBorrowed(Request $request, MostBorrowedReportService $service): JsonResponse
    {
        $q = $this->validated($request->query(), [
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);

        return response()->json($service->execute($q['from'], $q['to']));
    }
}
