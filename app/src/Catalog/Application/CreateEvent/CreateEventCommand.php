<?php

namespace App\Catalog\Application\CreateEvent;

use App\Shared\Bus\Command\CommandInterface;

final readonly class CreateEventCommand implements CommandInterface
{
    /**
     * @param list<CreateEventSession> $sessions
     */
    public function __construct(
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
        public ?int $sourceId = null,
        public ?string $externalId = null,
        public ?int $createdBy = null,
        public array $sessions = [],
    ) {
    }
}
