<?php

namespace App\Catalog\Application\ShowCatalog;

use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
readonly class CatalogShowHandler implements HandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function __invoke(CatalogShowQuery $query)
    {
        try {

        $result = $this->categoryRepository->findAll($query->onlyAvailable);

            return new CatalogShowResponse($result);
        } catch (Throwable $exception) {
            dd($exception->getMessage());
        }
    }
}
