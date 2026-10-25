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
 * Pinned timers: a name, project and tags a user can start with one click.
 */
class Version000094Date20261024200000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('timetracker_pinned_timer')) {
			return null;
		}

		$table = $schema->createTable('timetracker_pinned_timer');
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('user_uid', Types::STRING, [
			'notnull' => true,
			'length' => 128,
		]);
		$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('project_id', Types::INTEGER, [
			'notnull' => false,
		]);
		// Comma-separated tag ids, e.g. "3,7"
		$table->addColumn('tag_ids', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('sort_order', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('created_at', Types::INTEGER, [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id'], 'tt_pinned_timer_pk');
		$table->addIndex(['user_uid'], 'tt_pinned_user_idx');

		return $schema;
	}
}
