<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntity;
use DateTimeImmutable;

class City extends DomainEntity
{
    private ?int $id = null;

    public function __construct(
        private string $name,
        private string $slug,
        private bool $available,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $name,
        string $slug,
    ): self {
        return new self(
            name: $name,
            slug: $slug,
            available: false,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(string $name, string $slug): void
    {
        $this->name = $name;
        $this->slug = $slug;
    }

    public function enable(): void
    {
        $this->available = true;
    }

    public function disable(): void
    {
        $this->available = false;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
