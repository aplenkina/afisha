<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class ListSearchFactory
{
    public function __construct(private RequestStack $requests)
    {
    }

    #[AsTwigFunction('list_search')]
    public function create(int $budgetMs = 75): ListSearch
    {
        $parameters = $this->requests->getCurrentRequest()?->query->all() ?? [];
        $query = $parameters['q'] ?? '';
        $regex = filter_var($parameters['regex'] ?? false, FILTER_VALIDATE_BOOL);

        return new ListSearch(is_string($query) ? $query : '', $regex, $budgetMs);
    }
}
