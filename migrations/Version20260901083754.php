<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260901083754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bookings table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bookings (id INT AUTO_INCREMENT NOT NULL, ride_id INT NOT NULL, passenger_id INT NOT NULL, INDEX IDX_7A853C35302A8A70 (ride_id), INDEX IDX_7A853C354502E565 (passenger_id), UNIQUE INDEX uniq_booking_ride_passenger (ride_id, passenger_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C35302A8A70 FOREIGN KEY (ride_id) REFERENCES rides (id)');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C354502E565 FOREIGN KEY (passenger_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_7A853C35302A8A70');
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_7A853C354502E565');
        $this->addSql('DROP TABLE bookings');
    }
}
