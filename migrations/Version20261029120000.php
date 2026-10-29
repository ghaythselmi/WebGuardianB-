<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store hashed, expiring password reset tokens'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE password_reset_token (user_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_PASSWORD_RESET_TOKEN_HASH (token_hash), INDEX IDX_PASSWORD_RESET_TOKEN_EXPIRY (expires_at), PRIMARY KEY(user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_PASSWORD_RESET_TOKEN_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_token');
    }
}
