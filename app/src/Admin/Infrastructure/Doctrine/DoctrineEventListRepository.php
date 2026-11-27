<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine;

use App\Admin\Application\ListEvents\EventListItem;
use App\Admin\Application\ListEvents\EventListRepository;
use App\Admin\Application\ListEvents\ListEventsQuery;
use App\Catalog\Domain\EventStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(EventListRepository::class)]
final readonly class DoctrineEventListRepository implements EventListRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(ListEventsQuery $query, int $limit, int $offset): array
    {
        $connection = $this->entityManager->getConnection();
        $page = $connection->createQueryBuilder()
            ->select('e.id', 'e.title', 'e.slug', 'e.status', 'c.name AS city', 'cat.name AS category')
            ->from('event', 'e')
            ->leftJoin('e', 'city', 'c', 'c.id = e.city_id')
            ->leftJoin('e', 'category', 'cat', 'cat.id = e.category_id')
            ->orderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->applyFilters($page, $query);
        if (!$query->usesRegex() && '' !== $query->search) {
            $this->applySearch($page, $query->search);
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT page.*, sessions.session_count, sessions.first_session'
            .' FROM ('.$page->getSQL().') page'
            .' LEFT JOIN LATERAL (SELECT count(*) AS session_count, min(starts_at) AS first_session'
            .' FROM event_session WHERE event_id = page.id) sessions ON true ORDER BY page.id DESC',
            $page->getParameters(),
            $page->getParameterTypes(),
        );

        return array_map(static fn (array $row): EventListItem => new EventListItem(
            (int) $row['id'],
            $row['title'],
            $row['slug'],
            EventStatus::from($row['status']),
            $row['city'],
            $row['category'],
            (int) $row['session_count'],
            null === $row['first_session'] ? null : new \DateTimeImmutable($row['first_session']),
        ), $rows);
    }

    public function categoryNames(): array
    {
        return $this->entityManager->getConnection()->fetchAllKeyValue('SELECT id, name FROM category ORDER BY position, id');
    }

    private function applyFilters(QueryBuilder $page, ListEventsQuery $query): void
    {
        if (null !== $query->status) {
            $page->andWhere('e.status = :status')->setParameter('status', $query->status->value);
        } elseif ($query->onlyUnpublished) {
            $page->andWhere('e.status <> :published')->setParameter('published', EventStatus::Published->value);
        }

        if (null !== $query->createdBy) {
            $page->andWhere('e.created_by = :creator')->setParameter('creator', $query->createdBy, ParameterType::INTEGER);
        }

        if (null !== $query->categoryId) {
            $page->andWhere('e.category_id = :category')->setParameter('category', $query->categoryId, ParameterType::INTEGER);
        }
    }

    private function applySearch(QueryBuilder $page, string $search): void
    {
        $conditions = [
            'strpos(lower(e.title), lower(:search)) > 0',
            'strpos(lower(e.slug), lower(:search)) > 0',
            'strpos(CAST(e.id AS text), :search) > 0',
            'strpos(lower(e.status), lower(:search)) > 0',
            'strpos(lower(c.name), lower(:search)) > 0',
            'strpos(lower(cat.name), lower(:search)) > 0',
        ];

        if (preg_match('/[-\s‐‑‒–—−]/u', $search)) {
            $conditions[] = <<<'SQL'
                strpos(
                    trim(regexp_replace(lower(e.title), '[-[:space:]‐‑‒–—−]+', ' ', 'g')),
                    nullif(trim(regexp_replace(lower(:search), '[-[:space:]‐‑‒–—−]+', ' ', 'g')), '')
                ) > 0
                SQL;
        }

        $statuses = [];
        foreach (EventStatus::cases() as $status) {
            if (false !== mb_stripos($status->label(), $search)) {
                $statuses[] = $status->value;
            }
        }
        if ([] !== $statuses) {
            $conditions[] = 'e.status IN (:matching_statuses)';
            $page->setParameter('matching_statuses', $statuses, ArrayParameterType::STRING);
        }

        $page->andWhere($page->expr()->or(...$conditions))->setParameter('search', $search);
    }
}
