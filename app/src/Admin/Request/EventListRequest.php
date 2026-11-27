<?php

declare(strict_types=1);

namespace App\Admin\Request;

use App\Admin\Application\ListEvents\ListEventsQuery;
use App\Admin\Request\Validation\OptionalDatabaseId;
use App\Catalog\Domain\EventStatus;
use App\Shared\Presentation\ListSearch;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class EventListRequest
{
    private const string UNPUBLISHED = 'unpublished';

    public function __construct(
        #[Assert\Choice(callback: [self::class, 'allowedStatuses'])]
        public string $status = '',
        #[OptionalDatabaseId]
        public string $category = '',
        #[Assert\Length(max: ListSearch::MAX_QUERY_LENGTH)]
        #[Assert\Regex('/^[^\x00]*$/uD')]
        public string $q = '',
        public bool $regex = false,
        #[Assert\Positive]
        public int $page = 1,
    ) {
    }

    /** @return list<string> */
    public static function allowedStatuses(): array
    {
        return ['', self::UNPUBLISHED, ...array_column(EventStatus::cases(), 'value')];
    }

    public function toQuery(?int $createdBy): ListEventsQuery
    {
        return new ListEventsQuery(
            status: EventStatus::tryFrom($this->status),
            createdBy: $createdBy,
            categoryId: '' === $this->category ? null : (int) $this->category,
            onlyUnpublished: self::UNPUBLISHED === $this->status,
            search: $this->regex ? $this->q : trim($this->q),
            regex: $this->regex,
            page: $this->page,
        );
    }
}
