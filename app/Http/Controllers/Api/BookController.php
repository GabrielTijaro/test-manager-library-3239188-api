<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Exceptions\ApiException;
use App\Application\Services\CreateBookService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\GetBookService;
use App\Application\Services\GetCoverService;
use App\Application\Services\RemoveCoverService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\UpdateBookService;
use App\Application\Services\UploadCoverService;
use App\Http\Support\ValidatesInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BookController
{
    use ValidatesInput;

    public function index(Request $request, SearchBooksService $service): JsonResponse
    {
        $q = $this->validated($request->query(), [
            'q' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($service->execute(
            $q['q'] ?? null,
            isset($q['category_id']) ? strtolower($q['category_id']) : null,
            isset($q['page']) ? (int) $q['page'] : null,
            isset($q['size']) ? (int) $q['size'] : null,
        ));
    }

    public function show(string $id, GetBookService $service): JsonResponse
    {
        return response()->json($service->execute(strtolower($id)));
    }

    public function store(Request $request, CreateBookService $service): JsonResponse
    {
        $d = $this->validated($request->all(), [
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'isbn' => ['required', 'string'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($service->execute(
            $d['title'],
            $d['author'],
            $d['isbn'],
            strtolower($d['category_id']),
            (int) $d['total_copies'],
        ), 201);
    }

    public function update(string $id, Request $request, UpdateBookService $service): JsonResponse
    {
        $d = $this->validated($request->all(), [
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($service->execute(
            strtolower($id),
            $d['title'],
            $d['author'],
            strtolower($d['category_id']),
            (int) $d['total_copies'],
        ));
    }

    public function destroy(string $id, DeleteBookService $service): Response
    {
        $service->execute(strtolower($id));

        return response()->noContent();
    }

    public function uploadCover(string $id, Request $request, UploadCoverService $service): JsonResponse
    {
        $bytes = $this->readUpload($request);
        if ($bytes === null) {
            throw ApiException::validation('file', 'requerido o inválido', 'Debe enviar un archivo válido en el campo «file» (máximo 2 MB).');
        }

        return response()->json($service->execute(strtolower($id), $bytes));
    }

    public function removeCover(string $id, RemoveCoverService $service): Response
    {
        $service->execute(strtolower($id));

        return response()->noContent();
    }

    public function cover(string $id, GetCoverService $service): Response
    {
        $cover = $service->execute(strtolower($id));

        return response($cover['bytes'], 200, ['Content-Type' => $cover['mime']]);
    }

    /** Lee el binario subido; PHP no puebla $_FILES en PUT, de ahí el respaldo con request_parse_body (8.4+). */
    private function readUpload(Request $request): ?string
    {
        $file = $request->file('file');
        if ($file !== null && !is_array($file)) {
            return $file->isValid() ? (file_get_contents($file->getRealPath()) ?: null) : null;
        }

        if (
            $request->isMethod('PUT')
            && function_exists('request_parse_body')
            && str_starts_with((string) $request->header('Content-Type'), 'multipart/form-data')
        ) {
            try {
                [, $files] = request_parse_body();
            } catch (\Throwable) {
                return null;
            }
            $f = $files['file'] ?? null;
            if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && isset($f['tmp_name'])) {
                return file_get_contents($f['tmp_name']) ?: null;
            }
        }

        return null;
    }
}
