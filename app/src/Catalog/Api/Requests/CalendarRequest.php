<?php

declare(strict_types=1);

namespace App\Catalog\Api\Requests;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CalendarRequest
{
    public function __construct(
        #[Assert\Length(max: 16)]
        public string $period = 'weekend',
        #[Assert\Length(max: 64)]
        #[Assert\Regex('/^[\w-]*$/uD')]
        public string $city = '',
        public bool $free = false,
        #[Assert\Length(max: 64)]
        #[Assert\Regex('/^[\w&-]*$/uD')]
        public string $category = '',
        #[Assert\Length(max: 32)]
        public string $from = '',
        #[Assert\Length(max: 32)]
        public string $to = '',
        #[Assert\Length(max: 512)]
        public string $q = '',
        public int $page = 1,
    ) {
    }
}
