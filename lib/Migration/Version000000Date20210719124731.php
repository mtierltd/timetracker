<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000000Date20210719124731 extends SimpleMigrationStep {
        /** @var IDBConnection */
        protected $connection;

        public function __construct(IDBConnection $connection) {
                $this->connection = $connection;
        }

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
        public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('timetracker_client')) {
			$table = $schema->createTable('timetracker_client');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 64
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('timetracker_project')) {
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

		if (!$schema->hasTable('timetracker_user_to_project')) {
			$table = $schema->createTable('timetracker_user_to_project');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_uid', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('project_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('admin', 'integer', [
				'notnull' => true,
				'default' => 0,
				'length' => 4,
			]);
			$table->addColumn('access', 'integer', [
				'notnull' => true,
				'default' => 1,
				'length' => 4,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

			$table->setPrimaryKey(['id'], 'tt_u_to_p_id_idx');
		}

		if (!$schema->hasTable('timetracker_user_to_client')) {
			$table = $schema->createTable('timetracker_user_to_client');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_uid', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('client_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('admin', 'integer', [
				'notnull' => true,
				'default' => 0,
				'length' => 4,
			]);
			$table->addColumn('access', 'integer', [
				'notnull' => true,
				'default' => 1,
				'length' => 4,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

			$table->setPrimaryKey(['id'], 'tt_u_t_c_id_idx');
		}

		if (!$schema->hasTable('timetracker_work_interval')) {
			$table = $schema->createTable('timetracker_work_interval');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('details', 'string', [
				'notnull' => true,
				'default' => '',
				'length' => 1024,
			]);
			$table->addColumn('project_id', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('user_uid', 'text', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('start', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('duration', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('running', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

			$table->setPrimaryKey(['id'], 'tt_w_i_id_idx');
		}

		if (!$schema->hasTable('timetracker_tag')) {
			$table = $schema->createTable('timetracker_tag');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_uid', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

			$table->setPrimaryKey(['id']);
		}

		
		if (!$schema->hasTable('timetracker_lpa_tags')) {
			$table = $schema->createTable('timetracker_lpa_tags');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('project_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('timetracker_workint_to_tag')) {
			$table = $schema->createTable('timetracker_workint_to_tag');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('work_interval_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

                        $table->setPrimaryKey(['id'], 'tt_wi_to_tag_id_idx');
		}

		if (!$schema->hasTable('timetracker_timeline_entry')) {
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

		if (!$schema->hasTable('timetracker_timeline')) {
			$table = $schema->createTable('timetracker_timeline');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('status', 'text', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_uid', 'text', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('group1', 'text', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('group2', 'text', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('time_group', 'text', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('filter_projects', 'text', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('filter_clients', 'text', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('time_interval', 'text', [
				'notnull' => false,
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

			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('timetracker_goal')) {
			$table = $schema->createTable('timetracker_goal');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_uid', 'text', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('project_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('hours', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('interval', 'text', [
				'notnull' => true,
				'length' => 12,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);

			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}

        /**
         * @param IOutput $output
         * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
         * @param array $options
         * @since 13.0.0
         */
        public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$this->moveTimeTrackerWorkIntervalToTag();
		$this->moveTimeTrackerLockedProjectAllowedTag();
	}

	protected function moveTimeTrackerWorkIntervalToTag(): void {
                if (!$this->connection->tableExists('timetracker_workint_to_tag') ||
                    !$this->connection->tableExists('timetracker_workinterval_to_tag')) {
                        return;
                }

                $insert = $this->connection->getQueryBuilder();
                $insert->insert('timetracker_workint_to_tag')
                        ->values([
                                'id' => $insert->createParameter('id'),
                                'work_interval_id' => $insert->createParameter('work_interval_id'),
                                'tag_id' => $insert->createParameter('tag_id'),
                                'created_at' => $insert->createParameter('created_at'),
                        ]);

                $query = $this->connection->getQueryBuilder();
                $query->select('*')
                        ->from('timetracker_workint_to_tag');
                $result = $query->execute();
                if ($result->fetch()) {
                        $result->closeCursor();
                        return;
                }

                $query = $this->connection->getQueryBuilder();
                $query->select('*')
                        ->from('timetracker_workinterval_to_tag');
                $result = $query->execute();

                while ($row = $result->fetch()) {
                        $insert
                                ->setParameter('id', (int) $row['id'], IQueryBuilder::PARAM_INT)
                                ->setParameter('work_interval_id', (int) $row['work_interval_id'], IQueryBuilder::PARAM_INT)
                                ->setParameter('tag_id', (int) $row['tag_id'], IQueryBuilder::PARAM_INT)
                                ->setParameter('created_at', (int) $row['created_at'], IQueryBuilder::PARAM_INT)
			;
                        $insert->execute();
                }
                $result->closeCursor();
        }

	protected function moveTimeTrackerLockedProjectAllowedTag(): void {
                if (!$this->connection->tableExists('timetracker_lpa_tags') ||
                    !$this->connection->tableExists('timetracker_locked_project_allowed_tag')) {
                        return;
                }

                $insert = $this->connection->getQueryBuilder();
                $insert->insert('timetracker_lpa_tags')
                        ->values([
                                'id' => $insert->createParameter('id'),
                                'project_id' => $insert->createParameter('project_id'),
                                'tag_id' => $insert->createParameter('tag_id'),
                                'created_at' => $insert->createParameter('created_at'),
                        ]);

                $query = $this->connection->getQueryBuilder();
                $query->select('*')
                        ->from('timetracker_lpa_tags');
                $result = $query->execute();
                if ($result->fetch()) {
                        $result->closeCursor();
                        return;
                }

                $query = $this->connection->getQueryBuilder();
                $query->select('*')
                        ->from('timetracker_locked_project_allowed_tag');
                $result = $query->execute();

                while ($row = $result->fetch()) {
                        $insert
                                ->setParameter('id', (int) $row['id'], IQueryBuilder::PARAM_INT)
                                ->setParameter('project_id', (int) $row['project_id'], IQueryBuilder::PARAM_INT)
                                ->setParameter('tag_id', (int) $row['tag_id'], IQueryBuilder::PARAM_INT)
                                ->setParameter('created_at', (int) $row['created_at'], IQueryBuilder::PARAM_INT)
			;
                        $insert->execute();
                }
                $result->closeCursor();
        }
}
