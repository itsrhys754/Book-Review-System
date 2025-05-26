<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250220222138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE book DROP isbn, CHANGE googleBooksId google_books_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE review CHANGE rating rating NUMERIC(3, 1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE book ADD isbn VARCHAR(45) DEFAULT NULL, CHANGE google_books_id googleBooksId VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE review CHANGE rating rating NUMERIC(10, 1) NOT NULL');
    }
}
