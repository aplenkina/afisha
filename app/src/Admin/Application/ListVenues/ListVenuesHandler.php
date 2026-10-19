<?php

namespace App\Admin\Application\ListVenues;

use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListVenuesHandler implements HandlerInterface
{
    public function __construct(
        private VenueRepositoryInterface $venueRepository,
    ) {
    }

    public function __invoke(ListVenuesQuery $query): ListVenuesResponse
    {
        return new ListVenuesResponse($this->venueRepository->findAll($query->cityId));
    }
}
