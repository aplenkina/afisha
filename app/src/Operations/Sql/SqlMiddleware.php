<?php

declare(strict_types=1);

namespace App\Operations\Sql;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final readonly class SqlMiddleware implements Middleware
{
    public function __construct(private SqlJournal $journal)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->journal) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly SqlJournal $journal)
            {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                return new SqlConnection(parent::connect($params), $this->journal);
            }
        };
    }
}
