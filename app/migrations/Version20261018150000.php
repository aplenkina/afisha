<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'source: итог последнего запуска сборщика (время, статус, краткая сводка)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD last_run_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE source ADD last_run_status VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE source ADD last_run_summary TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP last_run_at');
        $this->addSql('ALTER TABLE source DROP last_run_status');
        $this->addSql('ALTER TABLE source DROP last_run_summary');
    }
}
