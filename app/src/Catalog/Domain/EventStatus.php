<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\BaseEnumTrait;

enum EventStatus: string
{
    use BaseEnumTrait;

    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
