<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntity;

class Source extends DomainEntity
{
    private ?int $id = null;

    // Итог последнего запуска пишет сборщик на Go; здесь он только читается.
    private ?\DateTimeImmutable $lastRunAt = null;

    private ?string $lastRunStatus = null;

    private ?string $lastRunSummary = null;

    public function __construct(
        private int $cityId,
        private string $name,
        private string $slug,
        private string $sourceUrl,
        private ?array $config,
        private bool $available,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $cityId,
        string $name,
        string $slug,
        string $sourceUrl,
        ?array $config = null,
    ): self {
        return new self(
            cityId: $cityId,
            name: $name,
            slug: $slug,
            sourceUrl: $sourceUrl,
            config: $config,
            available: false,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function update(int $cityId, string $name, string $slug, string $sourceUrl, ?array $config = null): void
    {
        $this->cityId = $cityId;
        $this->name = $name;
        $this->slug = $slug;
        $this->sourceUrl = $sourceUrl;
        $this->config = $config;
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

    public function getCityId(): int
    {
        return $this->cityId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getExternalSource(): string
    {
        return $this->sourceUrl;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getLastRunAt(): ?\DateTimeImmutable
    {
        return $this->lastRunAt;
    }

    /**
     * ok, empty (источник вернул 0 событий) или error.
     */
    public function getLastRunStatus(): ?string
    {
        return $this->lastRunStatus;
    }

    public function getLastRunSummary(): ?string
    {
        return $this->lastRunSummary;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
