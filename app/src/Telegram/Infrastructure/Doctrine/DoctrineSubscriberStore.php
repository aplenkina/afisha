<?php

namespace App\Telegram\Infrastructure\Doctrine;

use App\Telegram\Application\SubscriberStore;
use Doctrine\DBAL\Connection;

final readonly class DoctrineSubscriberStore implements SubscriberStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function selectCity(int $chatId, int $cityId): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO tg_subscriber (chat_id, city_id, active, created_at)
                VALUES (:chat, :city, true, now())
                ON CONFLICT (chat_id) DO UPDATE SET city_id = EXCLUDED.city_id
                SQL,
            ['chat' => $chatId, 'city' => $cityId],
        );
    }
}
