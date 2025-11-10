<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiConsoleBundle\Migration;

use Doctrine\DBAL\Schema\Schema;
use Mautic\Migrations\AbstractMauticMigration;

final class Version20231225000000 extends AbstractMauticMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('ai_log');

        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
        ]);

        $table->addColumn('user_id', 'integer', [
            'notnull' => false,
        ]);

        $table->addColumn('timestamp', 'datetime', [
            'notnull' => true,
        ]);

        $table->addColumn('prompt', 'text', [
            'notnull' => true,
        ]);

        $table->addColumn('model', 'string', [
            'length' => 255,
            'notnull' => false,
        ]);

        $table->addColumn('output', 'text', [
            'notnull' => false,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id'], 'idx_ai_log_user_id');
        $table->addIndex(['timestamp'], 'idx_ai_log_timestamp');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('ai_log');
    }
}