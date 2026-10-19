<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntity;

class EventSession extends DomainEntity
{
    private ?int $id = null;

    public function __construct(
        private readonly Event $event,
        private \DateTimeImmutable $startsAt,
        private ?\DateTimeImmutable $endsAt,
        private ?int $venueId,
        private ?string $ticketUrl,
        private SessionStatus $status,
    ) {
    }

    public function reschedule(\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): void
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
    }

    public function changeVenue(?int $venueId): void
    {
        $this->venueId = $venueId;
    }

    public function changeTicketUrl(?string $ticketUrl): void
    {
        $this->ticketUrl = $ticketUrl;
    }

    public function changeStatus(SessionStatus $status): void
    {
        $this->status = $status;
    }

    public function schedule(): void
    {
        $this->status = SessionStatus::Scheduled;
    }

    public function cancel(): void
    {
        $this->status = SessionStatus::Cancelled;
    }

    public function markSoldOut(): void
    {
        $this->status = SessionStatus::SoldOut;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getVenueId(): ?int
    {
        return $this->venueId;
    }

    public function getTicketUrl(): ?string
    {
        return $this->ticketUrl;
    }

    public function getStatus(): SessionStatus
    {
        return $this->status;
    }
}
