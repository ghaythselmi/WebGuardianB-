<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Persist account alert preferences and scheduled website check history'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD email_alerts_enabled TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE website ADD last_alerted_availability VARCHAR(16) DEFAULT NULL, ADD last_ssl_alert_level INT DEFAULT NULL');
        $this->addSql('CREATE TABLE website_check (id INT AUTO_INCREMENT NOT NULL, website_id INT NOT NULL, checked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', availability VARCHAR(16) NOT NULL, http_status_code INT DEFAULT NULL, response_time_ms INT DEFAULT NULL, ssl_status VARCHAR(16) NOT NULL, ssl_expires_in_days INT DEFAULT NULL, headers_score INT DEFAULT NULL, headers_grade VARCHAR(2) DEFAULT NULL, INDEX IDX_2D63F2EF43C9FEA (website_id), INDEX IDX_WEBSITE_CHECK_TIME (website_id, checked_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE website_check ADD CONSTRAINT FK_2D63F2EF43C9FEA FOREIGN KEY (website_id) REFERENCES website (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE website_check');
        $this->addSql('ALTER TABLE website DROP last_alerted_availability, DROP last_ssl_alert_level');
        $this->addSql('ALTER TABLE `user` DROP created_at, DROP email_alerts_enabled');
    }
}
