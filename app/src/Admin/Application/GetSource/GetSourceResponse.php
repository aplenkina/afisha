<?php

namespace App\Admin\Application\GetSource;

use App\Catalog\Domain\Source;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class GetSourceResponse implements ResponseInterface
{
    public function __construct(
        public Source $source,
    ) {
    }
}
