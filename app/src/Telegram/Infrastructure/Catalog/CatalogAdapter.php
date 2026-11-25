<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Catalog;

use App\Catalog\Domain\CityNotFound;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Telegram\Application\Digest;
use App\Telegram\Application\PreviewDigest;
use App\Telegram\Application\TelegramCatalog;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TelegramCatalog::class)]
final readonly class CatalogAdapter implements TelegramCatalog
{
    public function __construct(
        private CityRepositoryInterface $cities,
        private CategoryRepositoryInterface $categories,
        private EventRepositoryInterface $events,
    ) {
    }

    public function availableCityNames(): array
    {
        $names = [];
        foreach ($this->cities->findAll() as $city) {
            if ($city->isAvailable()) {
                $names[$city->getId()] = $city->getName();
            }
        }

        return $names;
    }

    public function availableCityName(int $id): ?string
    {
        if ($id > 2_147_483_647) {
            return null;
        }

        try {
            $city = $this->cities->findById($id);
        } catch (CityNotFound) {
            return null;
        }

        return $city->isAvailable() ? $city->getName() : null;
    }

    public function digest(PreviewDigest $query, \DateTimeImmutable $from, \DateTimeImmutable $until): Digest
    {
        $city = $this->cities->findBySlug($query->citySlug);
        if (!$city->isAvailable()) {
            throw new \InvalidArgumentException('Город недоступен в публичной афише.');
        }

        $category = null === $query->categoryAlias ? null : $this->categories->findByAlias($query->categoryAlias, onlyAvailable: true);
        $sessions = $this->events->findDigestSessions($from, $until, $city->getId(), $query->onlyFree, $category?->getId());

        return new Digest(
            $query->citySlug,
            $city->getName(),
            $category?->getAlias(),
            $query->days,
            $query->onlyFree,
            $from,
            $until,
            $sessions,
        );
    }
}
