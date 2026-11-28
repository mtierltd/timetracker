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
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Weeks or months a goal is skipped in, e.g. during vacation (#379).
 * The period is the week's Monday (Y-m-d) or the month (Y-m), like the
 * periods getGoals() calculates.
 */
class Version000094Date20261128000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('timetracker_goal_skip')) {
			return null;
		}
		$table = $schema->createTable('timetracker_goal_skip');
		$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true, 'length' => 4]);
		$table->addColumn('goal_id', Types::INTEGER, ['notnull' => true, 'length' => 4]);
		$table->addColumn('period', Types::STRING, ['notnull' => true, 'length' => 10]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['goal_id', 'period'], 'tt_goal_skip_period');
		return $schema;
	}
}
