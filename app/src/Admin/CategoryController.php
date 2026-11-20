<?php

namespace App\Admin;

use App\Admin\Application\GetCategory\GetCategoryQuery;
use App\Admin\Application\GetCategory\GetCategoryResponse;
use App\Catalog\Application\ChangeCategoryAvailability\ChangeCategoryAvailabilityCommand;
use App\Catalog\Application\CreateCategory\CreateCategoryCommand;
use App\Catalog\Application\DeleteCategory\DeleteCategoryCommand;
use App\Catalog\Application\ShowCatalog\CatalogShowQuery;
use App\Catalog\Application\ShowCatalog\CatalogShowResponse;
use App\Catalog\Domain\CategoryNotFound;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CategoryRequest;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/category', name: 'category')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private CategoryRepositoryInterface $categories,
    ) {
    }

    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(): Response
    {
        /** @var CatalogShowResponse $result */
        $result = $this->messageBus->dispatch(new CatalogShowQuery())->last(HandledStamp::class)->getResult();

        $twigResult = array_map(function ($item) {
            return ['id' => $item->getId(), 'name' => $item->getName(), 'alias' => $item->getAlias(), 'available' => $item->isAvailable()];
        }, $result->categories);

        return $this->render('/admin/category/index.html.twig', [
            'data' => $twigResult,
        ]);
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function create(#[MapRequestPayload] CategoryRequest $categoryRequest): Response
    {
        $id = $categoryRequest->id ? (int) $categoryRequest->id : null;
        try {
            $this->messageBus->dispatch(new CreateCategoryCommand(
                id: $id,
                name: $categoryRequest->name,
                alias: $categoryRequest->alias,
                parentId: $categoryRequest->parentId ? (int) $categoryRequest->parentId : null,
            ));
        } catch (HandlerFailedException $exception) {
            $reason = $exception->getPrevious();
            if (!$reason instanceof \InvalidArgumentException && !$reason instanceof CategoryNotFound) {
                throw $exception;
            }

            $this->addFlash('danger', $reason->getMessage());

            return null === $id
                ? $this->redirectToRoute('admin_category_new')
                : $this->redirectToRoute('admin_category_details', ['id' => $id]);
        }

        return $this->redirect('/admin/category');
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(): Response
    {
        return $this->render('/admin/category/details.html.twig', [
            'data' => [],
            'parents' => $this->parentOptions(),
        ]);
    }

    #[AdminRoute('/{id<\d+>}', name: 'details', options: ['methods' => 'GET'])]
    public function details(int $id): Response
    {
        /** @var GetCategoryResponse $result */
        $result = $this->messageBus->dispatch(new GetCategoryQuery($id))->last(HandledStamp::class)->getResult();

        return $this->render('/admin/category/details.html.twig', [
            'data' => [
                'id' => $result->category->getId(),
                'name' => $result->category->getName(),
                'alias' => $result->category->getAlias(),
                'parentId' => $result->category->getParent()?->getId(),
            ],
            'parents' => $this->parentOptions($id),
        ]);
    }

    private function parentOptions(?int $id = null): array
    {
        $parents = [];
        foreach ($this->categories->findAll() as $category) {
            if (null === $category->getParent() && $category->getId() !== $id) {
                $parents[] = $category;
            }
        }

        return $parents;
    }

    #[AdminRoute('/{id<\d+>}/availability', name: 'availability', options: ['methods' => 'POST'])]
    public function availability(int $id, Request $request): Response
    {
        $this->messageBus->dispatch(new ChangeCategoryAvailabilityCommand(
            id: $id,
            available: $request->request->getBoolean('available'),
        ));

        return $this->redirectToRoute('admin_category_index');
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id): Response
    {
        $this->messageBus->dispatch(new DeleteCategoryCommand($id));

        return $this->redirect('/admin/category');
    }
}
