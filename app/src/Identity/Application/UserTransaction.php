<?php

declare(strict_types=1);

namespace App\Identity\Application;

interface UserTransaction
{
    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function run(\Closure $operation): mixed;
}
