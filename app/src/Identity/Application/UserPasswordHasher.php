<?php

declare(strict_types=1);

namespace App\Identity\Application;

interface UserPasswordHasher
{
    public function hash(string $plainPassword): string;
}
