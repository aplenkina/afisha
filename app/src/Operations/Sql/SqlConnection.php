<?php

declare(strict_types=1);

namespace App\Operations\Sql;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class SqlConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly SqlJournal $journal)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        $start = hrtime(true);
        try {
            $statement = parent::prepare($sql);
        } catch (\Throwable $error) {
            $this->journal->record($sql, $start, false);
            throw $error;
        }

        return new class($statement, $this->journal, $sql) extends AbstractStatementMiddleware {
            public function __construct(Statement $statement, private readonly SqlJournal $journal, private readonly string $sql)
            {
                parent::__construct($statement);
            }

            public function execute(): Result
            {
                return $this->journal->measure($this->sql, parent::execute(...));
            }
        };
    }

    public function query(string $sql): Result
    {
        return $this->journal->measure($sql, fn () => parent::query($sql));
    }

    public function exec(string $sql): int|string
    {
        return $this->journal->measure($sql, fn () => parent::exec($sql));
    }
}
