<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260829141012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rides table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rides (id INT AUTO_INCREMENT NOT NULL, departure_city VARCHAR(100) NOT NULL, arrival_city VARCHAR(100) NOT NULL, departure_at DATETIME NOT NULL, available_seats INT NOT NULL, price NUMERIC(7, 2) NOT NULL, driver_id INT NOT NULL, INDEX IDX_9D4620A3C3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rides ADD CONSTRAINT FK_9D4620A3C3423909 FOREIGN KEY (driver_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rides DROP FOREIGN KEY FK_9D4620A3C3423909');
        $this->addSql('DROP TABLE rides');
    }
}
