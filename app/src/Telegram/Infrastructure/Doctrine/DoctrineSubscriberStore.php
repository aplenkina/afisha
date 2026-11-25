<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Doctrine;

use App\Telegram\Application\SubscriberStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SubscriberStore::class)]
final readonly class DoctrineSubscriberStore implements SubscriberStore
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function selectCity(int $chatId, int $cityId): void
    {
        $this->entityManager->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO tg_subscriber (chat_id, city_id, active, created_at)
                VALUES (:chat, :city, true, now())
                ON CONFLICT (chat_id) DO UPDATE SET city_id = EXCLUDED.city_id
                SQL,
            ['chat' => $chatId, 'city' => $cityId],
        );
    }
}
