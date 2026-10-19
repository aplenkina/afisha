<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntity;
use DateTimeImmutable;

class Venue extends DomainEntity
{
    private ?int $id = null;

    public function __construct(
        private int $cityId,
        private string $name,
        private string $address,
        private ?float $latitude,
        private ?float $longitude,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $cityId,
        string $name,
        string $address,
        ?float $latitude = null,
        ?float $longitude = null,
    ): self {
        return new self(
            cityId: $cityId,
            name: $name,
            address: $address,
            latitude: $latitude,
            longitude: $longitude,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(
        int $cityId,
        string $name,
        string $address,
        ?float $latitude,
        ?float $longitude,
    ): void {
        $this->cityId = $cityId;
        $this->name = $name;
        $this->address = $address;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCityId(): int
    {
        return $this->cityId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
