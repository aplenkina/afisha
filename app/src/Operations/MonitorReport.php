<?php

declare(strict_types=1);

namespace App\Operations;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MonitorReport
{
    public function __construct(
        public bool $siteHealthy,
        public ?bool $importHealthy = null,
        #[Assert\Positive]
        public ?int $lastSiteFailureAt = null,
    ) {
    }
}
