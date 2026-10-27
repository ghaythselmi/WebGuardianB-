<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Persist detailed website monitor results'; }

    public function up(Schema $schema): void
    {
        // Inspect the live database. The Schema passed to up() is Doctrine's target
        // schema and may already contain columns from the current entity mappings.
        $databaseSchema = $this->sm->introspectSchema();
        $this->addMissingColumns($databaseSchema, 'website', [
            'http_status_code' => 'INT DEFAULT NULL',
            'response_time_ms' => 'INT DEFAULT NULL',
            'final_url' => 'VARCHAR(2048) DEFAULT NULL',
            'page_title' => 'VARCHAR(255) DEFAULT NULL',
            'ssl_issuer' => 'VARCHAR(255) DEFAULT NULL',
            'ssl_expires_at' => 'VARCHAR(40) DEFAULT NULL',
            'security_headers_present' => 'JSON DEFAULT NULL',
            'security_headers_missing' => 'JSON DEFAULT NULL',
            'headers_score' => 'INT DEFAULT NULL',
            'headers_grade' => 'VARCHAR(2) DEFAULT NULL',
        ]);
        $this->addMissingColumns($databaseSchema, 'website_check', [
            'final_url' => 'VARCHAR(2048) DEFAULT NULL',
            'page_title' => 'VARCHAR(255) DEFAULT NULL',
            'ssl_issuer' => 'VARCHAR(255) DEFAULT NULL',
            'ssl_expires_at' => 'VARCHAR(40) DEFAULT NULL',
            'security_headers_present' => 'JSON DEFAULT NULL',
            'security_headers_missing' => 'JSON DEFAULT NULL',
        ]);
    }

    public function down(Schema $schema): void
    {
        // The up migration supports databases where some of these columns were
        // already present. Dropping them here could delete pre-existing data.
    }

    /** @param array<string, string> $columns */
    private function addMissingColumns(Schema $schema, string $tableName, array $columns): void
    {
        $table = $schema->getTable($tableName);
        foreach ($columns as $name => $definition) {
            if (!$table->hasColumn($name)) {
                $this->addSql(sprintf('ALTER TABLE `%s` ADD `%s` %s', $tableName, $name, $definition));
                // Keep the in-memory schema current so duplicate names in this migration
                // cannot be scheduled twice.
                $table->addColumn($name, str_starts_with($definition, 'VARCHAR') ? 'string' : (str_starts_with($definition, 'JSON') ? 'json' : 'integer'));
            }
        }
    }
}
