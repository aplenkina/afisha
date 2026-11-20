<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120110025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет связь карточки-дубля с основным событием';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD duplicate_of_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA72CC33300 FOREIGN KEY (duplicate_of_id) REFERENCES event (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3BAE0AA72CC33300 ON event (duplicate_of_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE event SET status = 'archived' WHERE status = 'duplicate'");
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA72CC33300');
        $this->addSql('DROP INDEX IDX_3BAE0AA72CC33300');
        $this->addSql('ALTER TABLE event DROP duplicate_of_id');
    }
}
