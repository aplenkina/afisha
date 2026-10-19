<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Support;

use App\Catalog\Domain\Source;
use App\Catalog\Domain\SourceNotFound;
use App\Catalog\Infrastructure\Repository\SourceRepositoryInterface;

final class InMemorySourceRepository implements SourceRepositoryInterface
{
    /** @var array<int, Source> */
    private array $items = [];
    private int $nextId = 1;

    public function add(Source $source): Source
    {
        $this->save($source);

        return $source;
    }

    #[\Override]
    public function save(Source $venue): void
    {
        $id = new \ReflectionProperty(Source::class, 'id');
        if (null === $id->getValue($venue)) {
            $id->setValue($venue, $this->nextId++);
        }
        $this->items[$id->getValue($venue)] = $venue;
    }

    #[\Override]
    public function delete(Source $venue): void
    {
        unset($this->items[$venue->getId()]);
    }

    #[\Override]
    public function findAll(?int $cityId = null): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Source $s) => null === $cityId || $s->getCityId() === $cityId,
        ));
    }

    #[\Override]
    public function existsByCity(int $cityId): bool
    {
        return [] !== $this->findAll($cityId);
    }

    #[\Override]
    public function findById(int $id): Source
    {
        return $this->items[$id] ?? throw new SourceNotFound($id);
    }

    #[\Override]
    public function findByCityAndName(int $cityId, string $name): ?Source
    {
        foreach ($this->items as $source) {
            if ($source->getCityId() === $cityId && $source->getName() === $name) {
                return $source;
            }
        }

        return null;
    }

    #[\Override]
    public function findBySlug(string $slug): ?Source
    {
        foreach ($this->items as $source) {
            if ($source->getSlug() === $slug) {
                return $source;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
