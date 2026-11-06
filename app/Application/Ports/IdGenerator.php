<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface IdGenerator
{
    /** UUID v4 como texto. */
    public function uuid(): string;
}
