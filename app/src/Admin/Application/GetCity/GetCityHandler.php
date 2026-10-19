<?php

namespace App\Admin\Application\GetCity;

use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCityHandler implements HandlerInterface
{
    public function __construct(
        private CityRepositoryInterface $cityRepository,
    ) {
    }

    public function __invoke(GetCityQuery $query): GetCityResponse
    {
        return new GetCityResponse($this->cityRepository->findById($query->id));
    }
}
