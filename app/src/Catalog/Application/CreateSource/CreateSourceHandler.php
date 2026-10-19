<?php

namespace App\Catalog\Application\CreateSource;

use App\Catalog\Domain\Source;
use App\Catalog\Domain\SourceSlugTaken;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\SourceRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Application\SlugGeneratorInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateSourceHandler implements HandlerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private CityRepositoryInterface $cityRepository,
        private FlusherInterface $flusher,
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function __invoke(CreateSourceCommand $command): int
    {
        $this->cityRepository->findById($command->cityId);

        $existing = $this->sourceRepository->findByCityAndName($command->cityId, $command->name);

        $slug = $this->slugGenerator->generate($command->slug ?: $command->name);

        $sameSlug = $this->sourceRepository->findBySlug($slug);
        if (null !== $sameSlug && $sameSlug->getId() !== $existing?->getId()) {
            throw new SourceSlugTaken($slug);
        }

        if (null !== $existing) {
            $existing->update(
                cityId: $command->cityId,
                name: $command->name,
                slug: $slug,
                sourceUrl: $command->sourceUrl,
                config: $command->config,
            );
            $this->flusher->flush();

            return $existing->getId();
        }

        $source = Source::create(
            cityId: $command->cityId,
            name: $command->name,
            slug: $slug,
            sourceUrl: $command->sourceUrl,
            config: $command->config,
        );

        $this->sourceRepository->save($source);
        $this->flusher->flush();

        return $source->getId();
    }
}
