<?php

namespace App\Catalog\Application\CreateCategory;

use App\Catalog\Domain\Category;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateCategoryHandler implements HandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private FlusherInterface $flusher
    ) {
    }

    public function __invoke(CreateCategoryCommand $command): void
    {
        if ($command->id !== null) {
            $category = $this->categoryRepository->findById($command->id);
            $category->update(
                name: $command->name,
                alias: $command->alias
            );
        } else {
            $category = Category::create(
                name: $command->name,
                alias: $command->alias,
                position: $this->getPosition(),
            );
        }

        $this->categoryRepository->save($category);

        $this->flusher->flush();
    }

    private function getPosition(): int
    {
        return $this->categoryRepository->count() + 1;
    }
}
