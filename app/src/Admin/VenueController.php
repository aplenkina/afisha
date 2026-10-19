<?php

namespace App\Admin;

use App\Admin\Application\GetVenue\GetVenueQuery;
use App\Admin\Application\GetVenue\GetVenueResponse;
use App\Admin\Application\ListCities\ListCitiesQuery;
use App\Admin\Application\ListCities\ListCitiesResponse;
use App\Admin\Application\ListVenues\ListVenuesQuery;
use App\Admin\Application\ListVenues\ListVenuesResponse;
use App\Admin\Request\VenueRequest;
use App\Catalog\Application\CreateVenue\CreateVenueCommand;
use App\Catalog\Application\DeleteVenue\DeleteVenueCommand;
use App\Catalog\Application\UpdateVenue\UpdateVenueCommand;
use App\Catalog\Domain\VenueNotFound;
use App\Shared\Domain\DomainError;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/venue', name: 'venue')]
#[IsGranted('ROLE_MODERATOR')]
class VenueController extends AbstractAdminController
{
    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(Request $request): Response
    {
        $cityId = $this->toInt($request->query->getString('city'));

        /** @var ListVenuesResponse $result */
        $result = $this->dispatch(new ListVenuesQuery($cityId));

        return $this->render('/admin/venue/index.html.twig', [
            'venues' => $result->venues,
            'cities' => $this->cities(),
            'cityId' => $cityId,
        ]);
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(): Response
    {
        return $this->render('/admin/venue/details.html.twig', [
            'venue' => null,
            'cities' => $this->cities(),
        ]);
    }

    #[AdminRoute('/{id<\d+>}', name: 'details', options: ['methods' => 'GET'])]
    public function details(int $id): Response
    {
        try {
            /** @var GetVenueResponse $result */
            $result = $this->dispatch(new GetVenueQuery($id));
        } catch (VenueNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->render('/admin/venue/details.html.twig', [
            'venue' => $result->venue,
            'cities' => $this->cities(),
        ]);
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function save(#[MapRequestPayload] VenueRequest $request): Response
    {
        $id = $this->toInt($request->id);

        try {
            if (null === $id) {
                $this->dispatch(new CreateVenueCommand(
                    cityId: (int) $request->cityId,
                    name: $request->name,
                    address: $request->address,
                    latitude: $this->toFloat($request->latitude),
                    longitude: $this->toFloat($request->longitude),
                ));
            } else {
                $this->dispatch(new UpdateVenueCommand(
                    id: $id,
                    cityId: (int) $request->cityId,
                    name: $request->name,
                    address: $request->address,
                    latitude: $this->toFloat($request->latitude),
                    longitude: $this->toFloat($request->longitude),
                ));
            }
        } catch (DomainError $error) {
            return $this->fail($error, null !== $id ? 'admin_venue_details' : 'admin_venue_new', null !== $id ? ['id' => $id] : []);
        }

        $this->addFlash('success', 'Площадка сохранена');

        return $this->redirectToRoute('admin_venue_index');
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id): Response
    {
        try {
            $this->dispatch(new DeleteVenueCommand($id));
            $this->addFlash('success', 'Площадка удалена');
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_venue_index');
        }

        return $this->redirectToRoute('admin_venue_index');
    }

    private function cities(): array
    {
        /** @var ListCitiesResponse $result */
        $result = $this->dispatch(new ListCitiesQuery());

        return $result->cities;
    }
}
