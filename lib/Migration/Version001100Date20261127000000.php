<?php

declare(strict_types=1);

/**
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\TimeTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Absence types and absences (#374). Dates are stored as Y-m-d strings:
 * absences cover whole days, independent of time zones.
 */
class Version001100Date20261127000000 extends SimpleMigrationStep {

	/** name, colour, counts as, deducts vacation, needs approval, visibility */
	private const DEFAULT_TYPES = [
		['Vacation', '#2e7d32', 'target_reduction', 1, 1, 'reason'],
		['Illness', '#c62828', 'target_reduction', 0, 0, 'busy_only'],
		['Special leave', '#6a1b9a', 'target_reduction', 0, 1, 'reason'],
		['Compensatory time off', '#00838f', 'target_reduction', 0, 1, 'reason'],
		['Business trip', '#ef6c00', 'work_time', 0, 1, 'reason'],
		['Training', '#1565c0', 'work_time', 0, 1, 'reason'],
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if (!$schema->hasTable('timetracker_absence_type')) {
			$table = $schema->createTable('timetracker_absence_type');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true, 'length' => 4]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('color', Types::STRING, ['notnull' => true, 'length' => 7, 'default' => '#757575']);
			// target_reduction: reduces the target hours (vacation, illness);
			// work_time: counts as working time (business trip, training)
			$table->addColumn('counts_as', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'target_reduction']);
			$table->addColumn('deducts_vacation', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('needs_approval', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			// reason: colleagues see the type; busy_only: they only see "absent"
			$table->addColumn('visibility', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'reason']);
			$table->addColumn('archived', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$changed = true;
		}

		if (!$schema->hasTable('timetracker_absence')) {
			$table = $schema->createTable('timetracker_absence');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true, 'length' => 4]);
			$table->addColumn('user_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('type_id', Types::INTEGER, ['notnull' => true, 'length' => 4]);
			$table->addColumn('start_date', Types::STRING, ['notnull' => true, 'length' => 10]);
			$table->addColumn('end_date', Types::STRING, ['notnull' => true, 'length' => 10]);
			$table->addColumn('half_day_start', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('half_day_end', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			// requested, approved, rejected, cancelled: absences are never deleted
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('note', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('decided_at', Types::INTEGER, ['notnull' => false, 'length' => 4]);
			$table->addColumn('decision_comment', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('created_at', Types::INTEGER, ['notnull' => true, 'length' => 4]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_uid', 'start_date'], 'tt_absence_user_start');
			$table->addIndex(['start_date', 'end_date'], 'tt_absence_dates');
			$changed = true;
		}

		return $changed ? $schema : null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'n'))->from('timetracker_absence_type');
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		if ($count > 0) {
			return;
		}
		foreach (self::DEFAULT_TYPES as [$name, $color, $countsAs, $deductsVacation, $needsApproval, $visibility]) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('timetracker_absence_type')->values([
				'name' => $qb->createNamedParameter($name),
				'color' => $qb->createNamedParameter($color),
				'counts_as' => $qb->createNamedParameter($countsAs),
				'deducts_vacation' => $qb->createNamedParameter($deductsVacation, IQueryBuilder::PARAM_INT),
				'needs_approval' => $qb->createNamedParameter($needsApproval, IQueryBuilder::PARAM_INT),
				'visibility' => $qb->createNamedParameter($visibility),
				'archived' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			])->executeStatement();
		}
	}
}
