<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

/** Error de negocio o de entrada con el cuerpo único del contrato (RNF-4). */
class ApiException extends RuntimeException
{
    /** @param list<array{field:string, issue:string}> $details */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function validation(string $field, string $issue, string $message = 'Los datos enviados no son válidos.'): self
    {
        return new self(422, 'VALIDATION_ERROR', $message, [['field' => $field, 'issue' => $issue]]);
    }

    public static function notFound(string $what = 'El recurso'): self
    {
        return new self(404, 'NOT_FOUND', $what . ' no existe.');
    }
}
