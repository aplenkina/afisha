<?php

namespace App\Admin;

use App\Admin\Application\GetSource\GetSourceQuery;
use App\Admin\Application\GetSource\GetSourceResponse;
use App\Admin\Application\ListCities\ListCitiesQuery;
use App\Admin\Application\ListCities\ListCitiesResponse;
use App\Admin\Application\ListSources\ListSourcesQuery;
use App\Admin\Application\ListSources\ListSourcesResponse;
use App\Admin\Request\SourceRequest;
use App\Catalog\Application\ChangeSourceAvailability\ChangeSourceAvailabilityCommand;
use App\Catalog\Application\CreateSource\CreateSourceCommand;
use App\Catalog\Application\DeleteSource\DeleteSourceCommand;
use App\Catalog\Application\UpdateSource\UpdateSourceCommand;
use App\Catalog\Domain\SourceNotFound;
use App\Shared\Domain\DomainError;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/source', name: 'source')]
#[IsGranted('ROLE_MODERATOR')]
class SourceController extends AbstractAdminController
{
    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(Request $request): Response
    {
        $cityId = $this->toInt($request->query->getString('city'));

        /** @var ListSourcesResponse $result */
        $result = $this->dispatch(new ListSourcesQuery($cityId));

        return $this->render('/admin/source/index.html.twig', [
            'sources' => $result->sources,
            'cities' => $this->cities(),
            'cityId' => $cityId,
        ]);
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(): Response
    {
        return $this->render('/admin/source/details.html.twig', [
            'source' => null,
            'cities' => $this->cities(),
        ]);
    }

    #[AdminRoute('/{id<\d+>}', name: 'details', options: ['methods' => 'GET'])]
    public function details(int $id): Response
    {
        try {
            /** @var GetSourceResponse $result */
            $result = $this->dispatch(new GetSourceQuery($id));
        } catch (SourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->render('/admin/source/details.html.twig', [
            'source' => $result->source,
            'cities' => $this->cities(),
        ]);
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function save(#[MapRequestPayload] SourceRequest $request): Response
    {
        $id = $this->toInt($request->id);
        $config = $this->decodeConfig($request->config);

        try {
            if (null === $id) {
                $this->dispatch(new CreateSourceCommand(
                    cityId: (int) $request->cityId,
                    name: $request->name,
                    slug: $request->slug,
                    sourceUrl: $request->sourceUrl,
                    config: $config,
                ));
            } else {
                $this->dispatch(new UpdateSourceCommand(
                    id: $id,
                    cityId: (int) $request->cityId,
                    name: $request->name,
                    slug: $request->slug,
                    sourceUrl: $request->sourceUrl,
                    config: $config,
                ));
            }
        } catch (DomainError $error) {
            return $this->fail($error, null !== $id ? 'admin_source_details' : 'admin_source_new', null !== $id ? ['id' => $id] : []);
        }

        $this->addFlash('success', 'Источник сохранен');

        return $this->redirectToRoute('admin_source_index');
    }

    #[AdminRoute('/{id<\d+>}/availability', name: 'availability', options: ['methods' => 'POST'])]
    public function availability(int $id, Request $request): Response
    {
        try {
            $this->dispatch(new ChangeSourceAvailabilityCommand(
                id: $id,
                available: $request->request->getBoolean('available'),
            ));
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_source_index');
        }

        return $this->redirectToRoute('admin_source_index');
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id): Response
    {
        try {
            $this->dispatch(new DeleteSourceCommand($id));
            $this->addFlash('success', 'Источник удален');
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_source_index');
        }

        return $this->redirectToRoute('admin_source_index');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeConfig(?string $json): ?array
    {
        if (null === $json || '' === trim($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    private function cities(): array
    {
        /** @var ListCitiesResponse $result */
        $result = $this->dispatch(new ListCitiesQuery());

        return $result->cities;
    }
}
