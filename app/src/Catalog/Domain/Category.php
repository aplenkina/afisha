<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntity;

class Category extends DomainEntity
{
    private ?int $id = null;

    public function __construct(
        private string $name,
        private string $alias,
        private int $position,
        private bool $available,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $name,
        string $alias,
        int $position,
    ): self {
        $createdAt = new \DateTimeImmutable();

        return new self(
            name: $name,
            alias: $alias,
            position: $position,
            available: false,
            createdAt: $createdAt
        );
    }

    public function update(
        string $name,
        string $alias,
    ) {
        $this->name = $name;
        $this->alias = $alias;
    }

    public function changeAvailability(bool $available): void
    {
        $this->available = $available;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}
