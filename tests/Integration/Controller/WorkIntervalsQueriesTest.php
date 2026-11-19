<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Db\Project;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\Tag;
use OCA\TimeTracker\Db\TagMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCA\TimeTracker\Db\WorkIntervalToTag;
use OCA\TimeTracker\Db\WorkIntervalToTagMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * The Timer list loads projects and tags for all entries at once, not per
 * entry (#338), with the same response as before.
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class WorkIntervalsQueriesTest extends TestCase {
	use CreatesAjaxController;

	/** @var string */
	private $user;
	/** @var array<string, int[]> */
	private $created = ['timetracker_project' => [], 'timetracker_tag' => []];

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'tt-queries-' . bin2hex(random_bytes(4));
	}

	protected function tearDown(): void {
		$db = \OCP\Server::get(IDBConnection::class);
		$qb = $db->getQueryBuilder();
		$ids = $qb->select('id')->from('timetracker_work_interval')
			->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($this->user)))
			->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
		if ($ids) {
			$qb = $db->getQueryBuilder();
			$qb->delete('timetracker_workint_to_tag')
				->where($qb->expr()->in('work_interval_id', $qb->createNamedParameter(array_map('intval', $ids), IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		$qb = $db->getQueryBuilder();
		$qb->delete('timetracker_work_interval')->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($this->user)))->executeStatement();
		foreach ($this->created as $table => $tableIds) {
			if ($tableIds) {
				$qb = $db->getQueryBuilder();
				$qb->delete($table)->where($qb->expr()->in('id', $qb->createNamedParameter($tableIds, IQueryBuilder::PARAM_INT_ARRAY)))->executeStatement();
			}
		}
		parent::tearDown();
	}

	public function testResponse(): void {
		$project = $this->project('Queries project ' . $this->user, '#123456');
		$zebra = $this->tag('zebra');
		$alpha = $this->tag('alpha');
		$day = gmmktime(9, 0, 0, 3, 2, 2026);

		$this->entry('With project and tags', $day, $project->getId(), [$zebra->getId(), $alpha->getId()]);
		$this->entry('Without project', $day + 3600, null, []);
		$this->entry('Deleted project', $day + 7200, 987654321, []);
		$this->entry('Deleted tag', $day + 10800, null, [987654321, $alpha->getId()]);

		$children = $this->children($day);

		$this->assertSame(['Queries project ' . $this->user, '#123456'], [$children['With project and tags']['projectName'], $children['With project and tags']['projectColor']]);
		// tags in the order they were added, not sorted
		$this->assertSame(['zebra', 'alpha'], array_map(fn ($t) => $t->getName(), $children['With project and tags']['tags']));
		$this->assertSame([null, null, []], [$children['Without project']['projectName'], $children['Without project']['projectColor'], $children['Without project']['tags']]);
		// a project that no longer exists: empty project fields instead of an error
		$this->assertSame([987654321, null], [$children['Deleted project']['projectId'], $children['Deleted project']['projectName']]);
		// a link to a deleted tag is left out
		$this->assertSame(['alpha'], array_map(fn ($t) => $t->getName(), $children['Deleted tag']['tags']));
	}

	public function testQueriesDontGrowWithEntries(): void {
		$project = $this->project('Queries project ' . $this->user, '#654321');
		$a = $this->tag('a');
		$b = $this->tag('b');
		$small = gmmktime(8, 0, 0, 4, 6, 2026);
		$large = gmmktime(8, 0, 0, 4, 8, 2026);
		for ($i = 0; $i < 3; $i++) {
			$this->entry('Small ' . $i, $small + $i * 600, $project->getId(), [$a->getId(), $b->getId()]);
		}
		for ($i = 0; $i < 60; $i++) {
			$this->entry('Large ' . $i, $large + $i * 600, $project->getId(), [$a->getId(), $b->getId()]);
		}

		$smallQueries = $this->queriesFor($small);
		$largeQueries = $this->queriesFor($large);

		$this->assertSame($smallQueries, $largeQueries, "3 entries: $smallQueries queries, 60 entries: $largeQueries queries");
		$this->assertLessThanOrEqual(6, $largeQueries);
	}

	/** The Timer list's entries of one day, by name. */
	private function children(int $dayStart): array {
		$data = $this->ajaxController($this->user, ['from' => $dayStart - 1, 'to' => $dayStart + 86400, 'tzoffset' => 0])->workIntervals()->getData();
		$children = [];
		foreach ($data['days'] as $groups) {
			foreach ($groups as $group) {
				foreach ($group['children'] as $child) {
					$children[$child['name']] = $child;
				}
			}
		}
		return $children;
	}

	/** Number of database queries for loading one day of the Timer list. */
	private function queriesFor(int $dayStart): int {
		$controller = $this->ajaxController($this->user, ['from' => $dayStart - 1, 'to' => $dayStart + 86400, 'tzoffset' => 0]);
		$connection = \OCP\Server::get(\OC\DB\Connection::class);
		$before = $connection->getStats()['executed'];
		$controller->workIntervals();
		return $connection->getStats()['executed'] - $before;
	}

	private function project(string $name, string $color): Project {
		$p = new Project();
		$p->setName($name);
		$p->setColor($color);
		$p->setCreatedByUserUid($this->user);
		$p->setCreatedAt(time());
		$p = \OCP\Server::get(ProjectMapper::class)->insert($p);
		$this->created['timetracker_project'][] = $p->getId();
		return $p;
	}

	private function tag(string $name): Tag {
		$t = new Tag();
		$t->setName($name);
		$t->setUserUid($this->user);
		$t->setCreatedAt(time());
		$t = \OCP\Server::get(TagMapper::class)->insert($t);
		$this->created['timetracker_tag'][] = $t->getId();
		return $t;
	}

	/** @param int[] $tagIds */
	private function entry(string $name, int $start, ?int $projectId, array $tagIds): void {
		$wi = new WorkInterval();
		$wi->setUserUid($this->user);
		$wi->setName($name);
		$wi->setStart($start);
		$wi->setDuration(600);
		$wi->setRunning(0);
		$wi->setProjectId($projectId);
		$wi = \OCP\Server::get(WorkIntervalMapper::class)->insert($wi);
		foreach ($tagIds as $tagId) {
			$link = new WorkIntervalToTag();
			$link->setWorkIntervalId($wi->getId());
			$link->setTagId($tagId);
			$link->setCreatedAt(time());
			\OCP\Server::get(WorkIntervalToTagMapper::class)->insert($link);
		}
	}
}
