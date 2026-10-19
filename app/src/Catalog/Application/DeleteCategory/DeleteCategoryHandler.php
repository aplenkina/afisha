<?php

namespace App\Catalog\Application\DeleteCategory;

use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteCategoryHandler implements HandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(DeleteCategoryCommand $command): void
    {
        $category = $this->categoryRepository->findById($command->id);
        $this->categoryRepository->delete($category);

        $this->flusher->flush();
    }
}
