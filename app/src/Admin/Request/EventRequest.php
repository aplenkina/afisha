<?php

namespace App\Admin\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class EventRequest
{
    /**
     * @param list<EventSessionRequest> $sessions
     */
    public function __construct(
        public ?string $id = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title = '',
        #[Assert\NotBlank]
        public string $categoryId = '',
        #[Assert\NotBlank]
        public string $cityId = '',
        public ?string $venueId = null,
        public ?string $description = null,
        public ?string $priceMin = null,
        public ?string $priceMax = null,
        public ?string $isFree = null,
        public ?string $ageLimit = null,
        #[Assert\Url]
        #[Assert\Length(max: 512)]
        public ?string $imageUrl = null,
        #[Assert\Url]
        #[Assert\Length(max: 512)]
        public ?string $sourceUrl = null,
        #[Assert\Url]
        #[Assert\Length(max: 512)]
        public ?string $ticketUrl = null,
        public array $sessions = [],
    ) {
    }
}
