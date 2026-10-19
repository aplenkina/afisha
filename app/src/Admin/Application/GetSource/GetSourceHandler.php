<?php

namespace App\Admin\Application\GetSource;

use App\Catalog\Infrastructure\Repository\SourceRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetSourceHandler implements HandlerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
    ) {
    }

    public function __invoke(GetSourceQuery $query): GetSourceResponse
    {
        return new GetSourceResponse($this->sourceRepository->findById($query->id));
    }
}
