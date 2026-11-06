<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\ListCategoriesService;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function index(ListCategoriesService $service): JsonResponse
    {
        return response()->json($service->execute());
    }
}
