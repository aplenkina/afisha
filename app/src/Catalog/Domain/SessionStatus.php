<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\BaseEnumTrait;

enum SessionStatus: string
{
    use BaseEnumTrait;

    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case SoldOut = 'sold_out';
}
