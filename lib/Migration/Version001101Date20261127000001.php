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
 * Vacation entitlement per user and year (#375).
 */
class Version001101Date20261127000001 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('timetracker_vacation_entitlement')) {
			return null;
		}
		$table = $schema->createTable('timetracker_vacation_entitlement');
		$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true, 'length' => 4]);
		$table->addColumn('user_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('year', Types::INTEGER, ['notnull' => true, 'length' => 4]);
		// days are stored in half days, to avoid floating point columns
		$table->addColumn('half_days', Types::INTEGER, ['notnull' => true, 'length' => 4, 'default' => 0]);
		$table->addColumn('carried_over_half_days', Types::INTEGER, ['notnull' => true, 'length' => 4, 'default' => 0]);
		// Y-m-d: carried-over days not taken by then expire
		$table->addColumn('carry_over_expiry', Types::STRING, ['notnull' => false, 'length' => 10]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_uid', 'year'], 'tt_vacation_user_year');
		return $schema;
	}
}
