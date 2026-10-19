<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Расширить name, slug и source_url в source: адреса источников не помещались в 64 символа';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ALTER name TYPE VARCHAR(128)');
        $this->addSql('ALTER TABLE source ALTER slug TYPE VARCHAR(128)');
        $this->addSql('ALTER TABLE source ALTER source_url TYPE VARCHAR(512)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ALTER name TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE source ALTER slug TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE source ALTER source_url TYPE VARCHAR(64)');
    }
}
