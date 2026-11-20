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
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(CreateCategoryCommand $command): void
    {
        $parent = null === $command->parentId ? null : $this->categoryRepository->findById($command->parentId);
        if (null !== $parent) {
            if ($parent->getId() === $command->id || null !== $parent->getParent()) {
                throw new \InvalidArgumentException('Можно выбрать только другую категорию верхнего уровня.');
            }
            if (null !== $command->id) {
                foreach ($this->categoryRepository->findAll() as $item) {
                    if ($item->getParent()?->getId() === $command->id) {
                        throw new \InvalidArgumentException('Перемести подкатегории, прежде чем менять родителя.');
                    }
                }
            }
        }
        if (null !== $command->id) {
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

        $category->setParent($parent);
        $this->categoryRepository->save($category);

        $this->flusher->flush();
    }

    private function getPosition(): int
    {
        return $this->categoryRepository->count() + 1;
    }
}
