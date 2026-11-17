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
 * Pinned timers (table name kept short for the index name limit): per user, a name with an optional project and tags that
 * starts a timer with one click (#290).
 */
class Version000095Date20261117000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('timetracker_pin')) {
			return null;
		}

		$table = $schema->createTable('timetracker_pin');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_uid', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('project_id', Types::BIGINT, [
			'notnull' => false,
			'unsigned' => true,
		]);
		// Comma-separated tag ids. Deleted or foreign tags are ignored when reading.
		$table->addColumn('tag_ids', Types::STRING, [
			'notnull' => false,
			'length' => 1024,
		]);
		$table->addColumn('sort_order', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_uid'], 'tt_pinned_user_idx');
		$table->addIndex(['project_id'], 'tt_pinned_project_idx');

		return $schema;
	}
}
