<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\MemberRepository;
use App\Application\Support\Page;

final class SearchMembersService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function execute(?string $q, ?int $page, ?int $size): array
    {
        [$page, $size] = Page::normalize($page, $size);
        $result = $this->members->search($q !== null ? trim($q) : null, $page, $size);

        return Page::make($result['items'], $result['total'], $page, $size);
    }
}
