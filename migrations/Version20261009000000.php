<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notes and the probe table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, body LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE zoo_probe (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE zoo_probe');
        $this->addSql('DROP TABLE note');
    }
}
