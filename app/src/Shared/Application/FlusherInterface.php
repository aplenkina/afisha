<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface FlusherInterface
{
    public function flush(): void;

    public function clear(): void;
}
