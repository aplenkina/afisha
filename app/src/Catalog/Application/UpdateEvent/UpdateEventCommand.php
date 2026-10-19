<?php

namespace App\Catalog\Application\UpdateEvent;

use App\Shared\Bus\Command\CommandInterface;

final readonly class UpdateEventCommand implements CommandInterface
{
    /**
     * @param list<UpdateEventSession> $sessions
     */
    public function __construct(
        public int $id,
        public string $title,
        public int $categoryId,
        public int $cityId,
        public ?int $venueId = null,
        public ?string $description = null,
        public ?string $priceMin = null,
        public ?string $priceMax = null,
        public bool $isFree = false,
        public ?int $ageLimit = null,
        public ?string $imageUrl = null,
        public ?string $sourceUrl = null,
        public ?string $ticketUrl = null,
        public array $sessions = [],
    ) {
    }
}
