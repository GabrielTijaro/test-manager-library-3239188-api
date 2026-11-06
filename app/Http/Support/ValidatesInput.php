<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Application\Exceptions\ApiException;
use Illuminate\Support\Facades\Validator;

trait ValidatesInput
{
    /**
     * Valida y devuelve los datos; si falla lanza el cuerpo único de error (422).
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $rules
     * @return array<string,mixed>
     */
    protected function validated(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $details = [];
            foreach ($validator->failed() as $field => $failedRules) {
                $details[] = ['field' => (string) $field, 'issue' => strtolower((string) array_key_first($failedRules))];
            }

            throw new ApiException(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', $details);
        }

        return $validator->validated();
    }
}
