<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ApiException;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;

/** GET /members/{id}: detalle con active_loans (libros sin devolver). */
final class GetMemberService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly LoanRepository $loans,
    ) {
    }

    public function execute(string $id): array
    {
        $member = $this->members->find($id) ?? throw ApiException::notFound('El socio');
        $member['active_loans'] = $this->loans->unreturnedCount($id);

        return $member;
    }
}
