<?php

namespace App\Catalog\Api;

use App\Catalog\Application\ShowCatalog\CatalogShowQuery;
use App\Catalog\Application\ShowCatalog\CatalogShowResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/', name: 'home', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        /** @var $result CatalogShowResponse */
        $result = $this->messageBus->dispatch(new CatalogShowQuery(onlyAvailable: true))->last(HandledStamp::class)->getResult();

        $categories = array_map(function ($item) {
            return ['id' => $item->getId(), 'name' => $item->getName(), 'alias' => $item->getAlias()];
        }, $result->categories);

        return $this->render('/site/home.html.twig', [
            'categories' => $categories,
        ]);
    }
}
