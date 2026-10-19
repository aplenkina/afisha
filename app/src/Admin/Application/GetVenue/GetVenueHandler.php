<?php

namespace App\Admin\Application\GetVenue;

use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetVenueHandler implements HandlerInterface
{
    public function __construct(
        private VenueRepositoryInterface $venueRepository,
    ) {
    }

    public function __invoke(GetVenueQuery $query): GetVenueResponse
    {
        return new GetVenueResponse($this->venueRepository->findById($query->id));
    }
}
