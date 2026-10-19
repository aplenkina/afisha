<?php

namespace App\Admin\Application\ListSources;

use App\Catalog\Infrastructure\Repository\SourceRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListSourcesHandler implements HandlerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
    ) {
    }

    public function __invoke(ListSourcesQuery $query): ListSourcesResponse
    {
        return new ListSourcesResponse($this->sourceRepository->findAll($query->cityId));
    }
}
