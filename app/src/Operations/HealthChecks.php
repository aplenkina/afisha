<?php

declare(strict_types=1);

namespace App\Operations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class HealthChecks
{
    public function __construct(private Connection $database)
    {
    }

    public function database(): bool
    {
        try {
            return 1 === (int) $this->database->fetchOne('SELECT 1');
        } catch (Exception) {
            return false;
        }
    }

    public function import(): bool
    {
        try {
            $unhealthy = $this->database->fetchOne(<<<'SQL'
                SELECT CASE WHEN count(*) = 0 OR count(*) FILTER (
                    WHERE last_run_status IS DISTINCT FROM 'ok'
                       OR last_run_at IS NULL
                       OR last_run_at < now() - interval '30 hours'
                ) > 0 THEN 1 ELSE 0 END
                FROM source
                WHERE available
                SQL);

            return 0 === (int) $unhealthy;
        } catch (Exception) {
            return false;
        }
    }
}
