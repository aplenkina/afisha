<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Application\MergeDuplicateEvents\MergeDuplicateEvents;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:event:mark-duplicates', description: 'Отмечает дубли опубликованных событий по названию, городу и времени сеансов')]
final readonly class MarkDuplicateEventsCommand
{
    public function __construct(
        private EventRepositoryInterface $events,
        private MergeDuplicateEvents $merger,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Сохранить изменения')] bool $apply = false): int
    {
        $pairs = [];
        foreach ($this->events->findPublishedDuplicateSeedIds() as $id) {
            $original = $this->merger->publishedOriginalId($id);
            if (null !== $original && $original < $id && (!$apply || $this->merger->markPublishedDuplicate($id))) {
                $pairs[] = [$id, $original];
                if ($apply) {
                    $this->flusher->clear();
                }
            }
        }

        if ([] !== $pairs) {
            $io->table(['Дубль', 'Основная карточка'], $pairs);
        }
        $io->success(sprintf($apply ? 'Помечено карточек: %d' : 'Найдено дублей: %d. Для применения запустите с --apply.', count($pairs)));

        return 0;
    }
}
