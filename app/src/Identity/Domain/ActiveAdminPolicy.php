<?php

declare(strict_types=1);

namespace App\Identity\Domain;

final class ActiveAdminPolicy
{
    public static function ensureAdminRemains(int $activeAdminCount): void
    {
        if ($activeAdminCount <= 1) {
            throw new LastAdminProtected();
        }
    }
}
