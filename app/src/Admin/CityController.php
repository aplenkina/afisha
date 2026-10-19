<?php

namespace App\Admin;

use App\Admin\Application\GetCity\GetCityQuery;
use App\Admin\Application\GetCity\GetCityResponse;
use App\Admin\Application\ListCities\ListCitiesQuery;
use App\Admin\Application\ListCities\ListCitiesResponse;
use App\Admin\Request\CityRequest;
use App\Catalog\Application\CreateCity\CreateCityCommand;
use App\Catalog\Application\DeleteCity\DeleteCityCommand;
use App\Catalog\Application\UpdateCity\UpdateCityCommand;
use App\Catalog\Domain\CityNotFound;
use App\Shared\Domain\DomainError;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/city', name: 'city')]
#[IsGranted('ROLE_ADMIN')]
class CityController extends AbstractAdminController
{
    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(): Response
    {
        /** @var ListCitiesResponse $result */
        $result = $this->dispatch(new ListCitiesQuery());

        return $this->render('/admin/city/index.html.twig', ['cities' => $result->cities]);
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(): Response
    {
        return $this->render('/admin/city/details.html.twig', ['city' => null]);
    }

    #[AdminRoute('/{id<\d+>}', name: 'details', options: ['methods' => 'GET'])]
    public function details(int $id): Response
    {
        try {
            /** @var GetCityResponse $result */
            $result = $this->dispatch(new GetCityQuery($id));
        } catch (CityNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->render('/admin/city/details.html.twig', ['city' => $result->city]);
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function save(#[MapRequestPayload] CityRequest $request): Response
    {
        $id = $this->toInt($request->id);

        try {
            if (null === $id) {
                $id = $this->dispatch(new CreateCityCommand(
                    name: $request->name,
                    slug: $this->toString($request->slug),
                    available: null !== $request->available,
                ));
            } else {
                $this->dispatch(new UpdateCityCommand(
                    id: $id,
                    name: $request->name,
                    slug: $this->toString($request->slug) ?? $request->name,
                    available: null !== $request->available,
                ));
            }
        } catch (DomainError $error) {
            return $this->fail($error, $request->id ? 'admin_city_details' : 'admin_city_new', $request->id ? ['id' => $request->id] : []);
        }

        $this->addFlash('success', 'Город сохранён');

        return $this->redirectToRoute('admin_city_index');
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id): Response
    {
        try {
            $this->dispatch(new DeleteCityCommand($id));
            $this->addFlash('success', 'Город удалён');
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_city_index');
        }

        return $this->redirectToRoute('admin_city_index');
    }
}
