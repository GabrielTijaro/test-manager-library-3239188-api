<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\CategoryRepository;

final class ListCategoriesService
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function execute(): array
    {
        return ['items' => $this->categories->all()];
    }
}
