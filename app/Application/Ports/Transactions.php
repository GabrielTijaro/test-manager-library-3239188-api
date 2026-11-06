<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface Transactions
{
    /**
     * Ejecuta $work como una sola operación atómica.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function run(callable $work): mixed;
}
