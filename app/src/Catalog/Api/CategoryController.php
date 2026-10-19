<?php

namespace App\Catalog\Api;

use App\Catalog\Application\GetCategory\GetCategoryQuery;
use App\Catalog\Application\GetCategory\GetCategoryResponse;
use App\Catalog\Application\ShowCatalog\CatalogShowQuery;
use App\Catalog\Application\ShowCatalog\CatalogShowResponse;
use App\Catalog\Application\ShowCategoryEvents\ShowCategoryEventsQuery;
use App\Catalog\Application\ShowCategoryEvents\ShowCategoryEventsResponse;
use App\Catalog\Domain\CategoryNotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/category', name: 'category_')]
class CategoryController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/', name: 'index', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('/site/category/index.html.twig', [
            'categories' => $this->getCategories(),
        ]);
    }

    #[Route('/{alias<[\w&-]+>}', name: 'page', methods: [Request::METHOD_GET])]
    public function page(string $alias): Response
    {
        try {
            /** @var GetCategoryResponse $result */
            $result = $this->messageBus->dispatch(new GetCategoryQuery($alias))->last(HandledStamp::class)->getResult();
        } catch (HandlerFailedException $exception) {
            if ($exception->getPrevious() instanceof CategoryNotFound) {
                throw $this->createNotFoundException($exception->getPrevious()->getMessage(), $exception);
            }

            throw $exception;
        }

        return $this->render('/site/category/page.html.twig', [
            'categories' => $this->getCategories(),
            'category' => [
                'id' => $result->category->getId(),
                'name' => $result->category->getName(),
                'alias' => $result->category->getAlias(),
            ],
            'events' => $this->getEvents($result->category->getId()),
        ]);
    }

    private function getCategories(): array
    {
        /** @var CatalogShowResponse $result */
        $result = $this->messageBus->dispatch(new CatalogShowQuery(onlyAvailable: true))->last(HandledStamp::class)->getResult();

        return array_map(function ($item) {
            return ['id' => $item->getId(), 'name' => $item->getName(), 'alias' => $item->getAlias()];
        }, $result->categories);
    }

    private function getEvents(int $categoryId): array
    {
        /** @var ShowCategoryEventsResponse $result */
        $result = $this->messageBus->dispatch(new ShowCategoryEventsQuery($categoryId))->last(HandledStamp::class)->getResult();

        return $result->events;
    }
}
