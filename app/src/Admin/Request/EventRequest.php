<?php

declare(strict_types=1);

namespace App\Admin\Request;

use App\Admin\Request\Validation\OptionalDatabaseId;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class EventRequest
{
    private const string PRICE_PATTERN = '/^[0-9]{1,8}(?:[.,][0-9]{1,2})?$/D';

    /** @param array<int, EventSessionRequest> $sessions */
    public function __construct(
        #[OptionalDatabaseId]
        public ?string $id = null,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $title = '',
        #[Assert\NotBlank]
        #[OptionalDatabaseId]
        public string $categoryId = '',
        #[Assert\NotBlank]
        #[OptionalDatabaseId]
        public string $cityId = '',
        #[OptionalDatabaseId]
        public ?string $venueId = null,
        #[Assert\Length(max: 65535)]
        public ?string $description = null,
        #[Assert\Regex(pattern: self::PRICE_PATTERN, normalizer: 'trim')]
        public ?string $priceMin = null,
        #[Assert\Regex(pattern: self::PRICE_PATTERN, normalizer: 'trim')]
        public ?string $priceMax = null,
        #[Assert\Choice(choices: ['1'])]
        public ?string $isFree = null,
        #[Assert\Regex('/^[0-9]{1,2}$/D')]
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
        #[Assert\Count(max: 150)]
        #[Assert\All([new Assert\Type(EventSessionRequest::class)])]
        #[Assert\Valid]
        public array $sessions = [],
    ) {
    }

    public function eventId(): ?int
    {
        return null === $this->id || '' === $this->id ? null : (int) $this->id;
    }
}
