<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store the verified Google identity for each user'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD google_subject VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_GOOGLE_SUBJECT ON `user` (google_subject)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_GOOGLE_SUBJECT ON `user`');
        $this->addSql('ALTER TABLE `user` DROP google_subject');
    }
}
