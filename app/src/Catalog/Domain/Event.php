<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Event extends DomainEntity
{
    /**
     * @var Collection<int, EventSession>
     */
    private Collection $sessions;

    private ?int $id = null;

    public function __construct(
        private string $title,
        private string $slug,
        private ?string $description,
        private int $categoryId,
        private int $cityId,
        private ?int $venueId,
        private ?string $priceMin,
        private ?string $priceMax,
        private bool $isFree,
        private ?int $ageLimit,
        private ?string $imageUrl,
        private ?string $sourceUrl,
        private ?string $ticketUrl,
        private EventStatus $status,
        private ?int $sourceId,
        private ?string $externalId,
        private ?int $createdBy,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->sessions = new ArrayCollection();
    }

    public static function create(
        string $title,
        string $slug,
        int $categoryId,
        int $cityId,
        ?int $venueId = null,
        ?string $description = null,
        ?string $priceMin = null,
        ?string $priceMax = null,
        bool $isFree = false,
        ?int $ageLimit = null,
        ?string $imageUrl = null,
        ?string $sourceUrl = null,
        ?string $ticketUrl = null,
        ?int $sourceId = null,
        ?string $externalId = null,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            title: $title,
            slug: $slug,
            description: $description,
            categoryId: $categoryId,
            cityId: $cityId,
            venueId: $venueId,
            priceMin: $priceMin,
            priceMax: $priceMax,
            isFree: $isFree,
            ageLimit: $ageLimit,
            imageUrl: $imageUrl,
            sourceUrl: $sourceUrl,
            ticketUrl: $ticketUrl,
            status: EventStatus::Draft,
            sourceId: $sourceId,
            externalId: $externalId,
            createdBy: $createdBy,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function update(
        string $title,
        string $slug,
        ?string $description,
        int $categoryId,
        int $cityId,
        ?int $venueId,
        ?string $priceMin,
        ?string $priceMax,
        bool $isFree,
        ?int $ageLimit,
        ?string $imageUrl,
        ?string $sourceUrl,
        ?string $ticketUrl,
    ): void {
        $this->title = $title;
        $this->slug = $slug;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->cityId = $cityId;
        $this->venueId = $venueId;
        $this->priceMin = $priceMin;
        $this->priceMax = $priceMax;
        $this->isFree = $isFree;
        $this->ageLimit = $ageLimit;
        $this->imageUrl = $imageUrl;
        $this->sourceUrl = $sourceUrl;
        $this->ticketUrl = $ticketUrl;
        $this->touch();
    }

    public function moveToDraft(): void
    {
        $this->changeStatus(EventStatus::Draft);
    }

    public function publish(): void
    {
        $this->changeStatus(EventStatus::Published);
    }

    public function reject(): void
    {
        $this->changeStatus(EventStatus::Rejected);
    }

    public function archive(): void
    {
        $this->changeStatus(EventStatus::Archived);
    }

    public function addSession(
        \DateTimeImmutable $startsAt,
        ?\DateTimeImmutable $endsAt = null,
        ?int $venueId = null,
        ?string $ticketUrl = null,
    ): EventSession {
        $session = new EventSession(
            event: $this,
            startsAt: $startsAt,
            endsAt: $endsAt,
            venueId: $venueId,
            ticketUrl: $ticketUrl,
            status: SessionStatus::Scheduled,
        );

        $this->sessions->add($session);
        $this->touch();

        return $session;
    }

    public function findSession(int $sessionId): ?EventSession
    {
        foreach ($this->sessions as $session) {
            if ($session->getId() === $sessionId) {
                return $session;
            }
        }

        return null;
    }

    public function removeSession(EventSession $session): void
    {
        $this->sessions->removeElement($session);
        $this->touch();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getCityId(): int
    {
        return $this->cityId;
    }

    public function getVenueId(): ?int
    {
        return $this->venueId;
    }

    public function getPriceMin(): ?string
    {
        return $this->priceMin;
    }

    public function getPriceMax(): ?string
    {
        return $this->priceMax;
    }

    public function isFree(): bool
    {
        return $this->isFree;
    }

    public function getAgeLimit(): ?int
    {
        return $this->ageLimit;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function getTicketUrl(): ?string
    {
        return $this->ticketUrl;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<EventSession>
     */
    public function getSessions(): array
    {
        return array_values($this->sessions->toArray());
    }

    private function changeStatus(EventStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
