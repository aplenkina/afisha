<?php

namespace App\Catalog\Application\ShowCategoryEvents;

final readonly class CategoryEventView
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public \DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public string $cityName,
        public ?string $venueName,
        public ?string $address,
        public ?string $priceMin,
        public ?string $priceMax,
        public bool $isFree,
        public ?string $ticketUrl,
        public ?string $sourceUrl,
    ) {
    }

    public function priceLabel(): ?string
    {
        if ($this->isFree) {
            return 'Бесплатно';
        }

        $min = $this->formatPrice($this->priceMin);
        $max = $this->formatPrice($this->priceMax);

        return match (true) {
            null !== $min && null !== $max && $min !== $max => sprintf('%s – %s BYN', $min, $max),
            null !== $min => sprintf('от %s BYN', $min),
            null !== $max => sprintf('до %s BYN', $max),
            default => null,
        };
    }

    private function formatPrice(?string $price): ?string
    {
        if (null === $price || '' === $price) {
            return null;
        }

        $value = (float) $price;

        return $value == floor($value) ? (string) (int) $value : number_format($value, 2, ',', '');
    }
}
