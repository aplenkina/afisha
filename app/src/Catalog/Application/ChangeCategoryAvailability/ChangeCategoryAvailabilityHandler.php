<?php

namespace App\Catalog\Application\ChangeCategoryAvailability;

use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeCategoryAvailabilityHandler implements HandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(ChangeCategoryAvailabilityCommand $command): void
    {
        $this->categoryRepository->findById($command->id)->changeAvailability($command->available);
        $this->flusher->flush();
    }
}
