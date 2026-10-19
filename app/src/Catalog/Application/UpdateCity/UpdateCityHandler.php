<?php

namespace App\Catalog\Application\UpdateCity;

use App\Catalog\Domain\CitySlugTaken;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Application\SlugGeneratorInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCityHandler implements HandlerInterface
{
    public function __construct(
        private CityRepositoryInterface $cityRepository,
        private SlugGeneratorInterface $slugGenerator,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(UpdateCityCommand $command): void
    {
        $city = $this->cityRepository->findById($command->id);
        $slug = $this->slugGenerator->generate($command->slug);

        if ($slug !== $city->getSlug() && $this->cityRepository->existsBySlug($slug)) {
            throw new CitySlugTaken($slug);
        }

        $city->update($command->name, $slug);

        if ($command->available) {
            $city->enable();
        } else {
            $city->disable();
        }

        $this->flusher->flush();
    }
}
