<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Exceptions\DuplicateValueException;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\MemberRepository;

/** HU-05 · Crear socio. */
final class CreateMemberService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly IdGenerator $ids,
    ) {
    }

    public function execute(string $fullName, string $documentNumber): array
    {
        $fullName = trim($fullName);
        $documentNumber = trim($documentNumber);
        if ($fullName === '') {
            throw ApiException::validation('full_name', 'requerido');
        }
        if ($documentNumber === '') {
            throw ApiException::validation('document_number', 'requerido');
        }

        $member = ['id' => $this->ids->uuid(), 'full_name' => $fullName, 'document_number' => $documentNumber];
        try {
            $this->members->insert($member);
        } catch (DuplicateValueException) {
            throw new ApiException(409, 'DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.', [['field' => 'document_number', 'issue' => 'repetido']]);
        }

        return $member;
    }
}
