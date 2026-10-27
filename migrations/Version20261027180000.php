<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027180000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store website availability notifications for users'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE user_notification (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(24) NOT NULL, title VARCHAR(180) NOT NULL, message VARCHAR(512) NOT NULL, website_url VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', read_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_USER_NOTIFICATION_USER_CREATED (user_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_USER_NOTIFICATION_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_notification');
    }
}
