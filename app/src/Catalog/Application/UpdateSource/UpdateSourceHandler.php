<?php

namespace App\Catalog\Application\UpdateSource;

use App\Catalog\Domain\SourceSlugTaken;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\SourceRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Application\SlugGeneratorInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSourceHandler implements HandlerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private CityRepositoryInterface $cityRepository,
        private FlusherInterface $flusher,
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function __invoke(UpdateSourceCommand $command): void
    {
        $source = $this->sourceRepository->findById($command->id);
        $this->cityRepository->findById($command->cityId);

        // Сборщики находят источник по slug, поэтому пустой slug не затирает прежний.
        $slug = null !== $command->slug && '' !== $command->slug
            ? $this->slugGenerator->generate($command->slug)
            : $source->getSlug();

        $other = $this->sourceRepository->findBySlug($slug);
        if (null !== $other && $other->getId() !== $source->getId()) {
            throw new SourceSlugTaken($slug);
        }

        $source->update(
            cityId: $command->cityId,
            name: $command->name,
            slug: $slug,
            sourceUrl: $command->sourceUrl,
            config: $command->config,
        );

        $this->flusher->flush();
    }
}
