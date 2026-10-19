<?php

namespace App\Catalog\Application\GetCategory;

use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetCategoryHandler implements HandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function __invoke(GetCategoryQuery $query): GetCategoryResponse
    {
        return new GetCategoryResponse($this->categoryRepository->findByAlias($query->alias, onlyAvailable: true));
    }
}
