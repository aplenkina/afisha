<?php

namespace App\Catalog\Application\CreateCity;

use App\Catalog\Domain\City;
use App\Catalog\Domain\CitySlugTaken;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Application\SlugGeneratorInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateCityHandler implements HandlerInterface
{
    public function __construct(
        private CityRepositoryInterface $cityRepository,
        private SlugGeneratorInterface $slugGenerator,
        private FlusherInterface $flusher,
    ) {
    }

    /**
     * @throws CitySlugTaken
     */
    public function __invoke(CreateCityCommand $command): int
    {
        $slug = $this->slugGenerator->generate($command->slug ?? $command->name);

        if ($this->cityRepository->existsBySlug($slug)) {
            throw new CitySlugTaken($slug);
        }

        $city = City::create(
            name: $command->name,
            slug: $slug,
        );

        if ($command->available) {
            $city->enable();
        }

        $this->cityRepository->save($city);
        $this->flusher->flush();

        return $city->getId();
    }
}
