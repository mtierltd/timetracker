<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20210719192031 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
        public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
                $schema = $schemaClosure();

                if ($schema->hasTable('timetracker_project')) {
                        $table = $schema->getTable('timetracker_project');
                        if ($table->hasColumn('created_by_user_id')) {
                                $schema->dropTable('timetracker_project');
                                $table = $schema->createTable('timetracker_project');
                                $table->addColumn('id', 'integer', [
                                        'autoincrement' => true,
                                        'notnull' => true,
                                ]);
                                $table->addColumn('name', 'string', [
                                        'notnull' => true,
                                        'length' => 64,
                                ]);
                                $table->addColumn('client_id', 'integer', [
                                        'notnull' => false,
                                        'length' => 4,
                                ]);
                                $table->addColumn('created_by_user_uid', 'string', [
                                        'notnull' => true,
                                        'length' => 128,
                                ]);
                                $table->addColumn('locked', 'integer', [
                                        'notnull' => false,
                                        'default' => 0,
                                        'length' => 4,
                                ]);
                                $table->addColumn('archived', 'integer', [
                                        'notnull' => false,
                                        'default' => 0,
                                        'length' => 4,
                                ]);
                                $table->addColumn('created_at', 'integer', [
                                        'notnull' => true,
                                        'length' => 4,
                                ]);
                                $table->addColumn('color', 'string', [
                                        'notnull' => true,
                                        'default' => '#ffffff',
                                        'length' => 7,
                                ]);

                                $table->setPrimaryKey(['id']);
                        }
                }

                if ($schema->hasTable('timetracker_timeline_entry')) {
                        $table = $schema->getTable('timetracker_timeline_entry');
                        if ($table->hasColumn('user_id')) {
                                $schema->dropTable('timetracker_timeline_entry');
                                $table = $schema->createTable('timetracker_timeline_entry');
                                $table->addColumn('id', 'integer', [
                                        'autoincrement' => true,
                                        'notnull' => true,
                                ]);
                                $table->addColumn('timeline_id', 'integer', [
                                        'notnull' => false,
                                        'length' => 4,
                                ]);
                                $table->addColumn('user_uid', 'text', [
                                        'notnull' => true,
                                        'length' => 128,
                                ]);
                                $table->addColumn('name', 'text', [
                                        'notnull' => true,
                                        'length' => 64,
                                ]);
                                $table->addColumn('project_name', 'text', [
                                        'notnull' => true,
                                        'length' => 64,
                                ]);
                                $table->addColumn('client_name', 'text', [
                                        'notnull' => true,
                                        'length' => 64,
                                ]);
                                $table->addColumn('time_interval', 'text', [
                                        'notnull' => true,
                                        'length' => 64,
                                ]);
                                $table->addColumn('total_duration', 'text', [
                                        'notnull' => true,
                                        'length' => 64,
                                ]);
                                $table->addColumn('created_at', 'integer', [
                                        'notnull' => true,
                                        'length' => 4,
                                ]);

                                $table->setPrimaryKey(['id'], 'tt_t_e_id_idx');
                        }
                }

		return $schema;
	}
}
