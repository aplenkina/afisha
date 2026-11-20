<?php

namespace App\Catalog\Application\MergeDuplicateEvents;

use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventNotFound;
use App\Catalog\Domain\EventStatus;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use Doctrine\DBAL\Connection;

final readonly class MergeDuplicateEvents
{
    public function __construct(
        private EventRepositoryInterface $events,
        private FlusherInterface $flusher,
        private Connection $database,
    ) {
    }

    public function merge(int $id, int $targetId): void
    {
        $this->database->transactional(function () use ($id, $targetId): void {
            $source = $this->lockEvent($id);
            $candidates = array_map('intval', array_column($this->events->findDuplicateCandidates($id), 'id'));
            if (!in_array($source->getStatus(), [EventStatus::Draft, EventStatus::Published], true) || !in_array($targetId, $candidates, true)) {
                throw new \InvalidArgumentException('Обновите карточку и выберите подходящее основное событие.');
            }

            $target = $this->lockEventRow($targetId);
            if (!in_array($target->getStatus(), [EventStatus::Published, EventStatus::Draft], true)
                || (EventStatus::Published === $source->getStatus() && EventStatus::Draft === $target->getStatus())) {
                throw new \InvalidArgumentException('Основное событие больше недоступно для объединения.');
            }

            $this->markDuplicateOf($source, $target);
        });
    }

    public function publish(int $id): bool
    {
        return $this->database->transactional(function () use ($id): bool {
            $source = $this->lockEvent($id);
            if (EventStatus::Duplicate === $source->getStatus()) {
                throw new \InvalidArgumentException('Верните дубль в черновики, чтобы изменить его статус публикации.');
            }

            $targetId = $this->publishedOriginalId($id);
            if (null !== $targetId && (EventStatus::Published !== $source->getStatus() || $targetId < $id)) {
                $target = $this->lockEventRow($targetId);
                if (EventStatus::Published === $target->getStatus()) {
                    $this->markDuplicateOf($source, $target);

                    return false;
                }
            }

            $source->publish();
            $this->flusher->flush();

            return true;
        });
    }

    public function publishedOriginalId(int $id): ?int
    {
        $candidate = $this->events->findDuplicateCandidates($id, publishedOnly: true)[0]['id'] ?? null;

        return null === $candidate ? null : (int) $candidate;
    }

    public function markPublishedDuplicate(int $id): bool
    {
        return $this->database->transactional(function () use ($id): bool {
            $source = $this->lockEvent($id);
            $targetId = $this->publishedOriginalId($id);
            if (EventStatus::Published !== $source->getStatus() || null === $targetId || $targetId >= $id) {
                return false;
            }

            $target = $this->lockEventRow($targetId);
            if (EventStatus::Published !== $target->getStatus()) {
                return false;
            }

            $this->markDuplicateOf($source, $target);

            return true;
        });
    }

    private function lockEvent(int $id): Event
    {
        // Serialize publishing and merging within a city before choosing an original.
        $cityId = $this->database->fetchOne('SELECT city_id FROM event WHERE id = ?', [$id]);
        if (false === $cityId) {
            throw new EventNotFound($id);
        }
        $this->database->fetchOne('SELECT id FROM city WHERE id = ? FOR NO KEY UPDATE', [$cityId]);

        return $this->lockEventRow($id);
    }

    private function lockEventRow(int $id): Event
    {
        $this->database->fetchOne('SELECT id FROM event WHERE id = ? FOR NO KEY UPDATE', [$id]);

        return $this->events->findById($id);
    }

    private function markDuplicateOf(Event $source, Event $target): void
    {
        $source->markDuplicate($target);
        foreach ($this->events->findDuplicatesOf($source) as $duplicate) {
            $duplicate->markDuplicate($target);
        }
        $this->flusher->flush();
    }
}
