<?php

namespace App\Admin\Application\ListCities;

use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListCitiesHandler implements HandlerInterface
{
    public function __construct(
        private CityRepositoryInterface $cityRepository,
    ) {
    }

    public function __invoke(ListCitiesQuery $query): ListCitiesResponse
    {
        return new ListCitiesResponse($this->cityRepository->findAll());
    }
}
