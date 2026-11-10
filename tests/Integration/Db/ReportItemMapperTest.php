<?php

namespace OCA\TimeTracker\Tests\Integration\Db;

use OCA\TimeTracker\Db\ReportItem;
use OCA\TimeTracker\Db\ReportItemMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * Runs the report query against the real database (SQLite, MySQL/MariaDB or
 * PostgreSQL, whatever the Nextcloud under test uses). Every database must
 * produce exactly these results.
 *
 * Each test creates its own users, clients, projects and entries and removes
 * them afterwards; queries are limited to that data, so existing data in the
 * database doesn't matter.
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class ReportItemMapperTest extends TestCase {

	private const BERLIN = 'Europe/Berlin';

	/** @var IDBConnection */
	private $db;
	/** @var ReportItemMapper */
	private $mapper;
	/** @var string */
	private $prefix;
	/** @var int[] */
	private $projectIds = [];
	/** @var int[] */
	private $clientIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OCP\Server::get(IDBConnection::class);
		$this->mapper = \OCP\Server::get(ReportItemMapper::class);
		$this->prefix = 'tt-it-' . bin2hex(random_bytes(4)) . '-';
	}

	protected function tearDown(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('timetracker_work_interval')
			->where($qb->expr()->like('user_uid', $qb->createNamedParameter($this->prefix . '%')))
			->executeStatement();
		if ($this->projectIds) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('timetracker_project')
				->where($qb->expr()->in('id', $qb->createNamedParameter($this->projectIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		if ($this->clientIds) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('timetracker_client')
				->where($qb->expr()->in('id', $qb->createNamedParameter($this->clientIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
	}

	// --- time groups ---

	public function testDayGroupsFollowTheUserTimeZone(): void {
		$user = $this->user('alice');
		// 23:30 and 00:30 in Berlin (UTC+1 in March before the switch)
		$this->entry($user, 'Late', '2026-03-10 22:30', 1800);
		$this->entry($user, 'Early', '2026-03-10 23:30', 1800);

		$this->assertSame(
			[['2026-03-10', 1800], ['2026-03-11', 1800]],
			$this->pick($this->report($user, 'day', '', '', self::BERLIN), 'ftime', 'totalDuration')
		);
		$this->assertSame(
			[['2026-03-10', 3600]],
			$this->pick($this->report($user, 'day', '', '', 'UTC'), 'ftime', 'totalDuration')
		);
	}

	public function testDayGroupsAcrossTheSwitchToSummerTime(): void {
		$user = $this->user('alice');
		// Berlin switches to UTC+2 on 29 March 2026: 23:30 on the 29th, then 00:30 on the 30th
		$this->entry($user, 'Before midnight', '2026-03-29 21:30', 600);
		$this->entry($user, 'After midnight', '2026-03-29 22:30', 600);

		$this->assertSame(
			['2026-03-29', '2026-03-30'],
			array_column($this->pick($this->report($user, 'day', '', '', self::BERLIN), 'ftime'), 0)
		);
	}

	public function testIsoWeeksAcrossTheYearBoundary(): void {
		$user = $this->user('alice');
		$this->entry($user, 'Sunday', '2024-12-29 12:00', 100);   // last day of 2024-W52
		$this->entry($user, 'Monday', '2024-12-30 12:00', 200);   // first day of 2025-W01
		$this->entry($user, 'Sunday', '2025-01-05 12:00', 300);   // last day of 2025-W01
		$this->entry($user, 'Monday', '2025-01-06 12:00', 400);   // first day of 2025-W02

		$this->assertSame(
			[['2024-W52', 100], ['2025-W01', 500], ['2025-W02', 400]],
			$this->pick($this->report($user, 'week', '', '', self::BERLIN), 'ftime', 'totalDuration')
		);
	}

	public function testMonthsAndYearsAtNewYearInTheUserTimeZone(): void {
		$user = $this->user('alice');
		$this->entry($user, 'Old year', '2025-12-31 12:00', 100);
		$this->entry($user, 'New year', '2025-12-31 23:30', 200);   // 00:30 on 1 January in Berlin

		$this->assertSame(
			[['2025-12', 100], ['2026-01', 200]],
			$this->pick($this->report($user, 'month', '', '', self::BERLIN), 'ftime', 'totalDuration')
		);
		$this->assertSame(
			[['2025', 100], ['2026', 200]],
			$this->pick($this->report($user, 'year', '', '', self::BERLIN), 'ftime', 'totalDuration')
		);
		$this->assertSame(
			[['2025', 300]],
			$this->pick($this->report($user, 'year', '', '', 'UTC'), 'ftime', 'totalDuration')
		);
	}

	public function testTimeGroupLabels(): void {
		$berlin = new \DateTimeZone(self::BERLIN);
		$at = fn (string $utc) => (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->getTimestamp();
		$this->assertSame('2025-W01', ReportItemMapper::timeGroupLabel($at('2024-12-30 00:00'), 'week', $berlin));
		$this->assertSame('2026-W53', ReportItemMapper::timeGroupLabel($at('2026-12-31 12:00'), 'week', $berlin));
		$this->assertSame('2020-W53', ReportItemMapper::timeGroupLabel($at('2021-01-03 12:00'), 'week', $berlin));
		$this->assertSame('2026-01-01', ReportItemMapper::timeGroupLabel($at('2025-12-31 23:00'), 'day', $berlin));
		$this->assertSame('2025-12-31', ReportItemMapper::timeGroupLabel($at('2025-12-31 23:00'), 'day', new \DateTimeZone('UTC')));
	}

	// --- grouping by columns ---

	public function testGroupByProjectSumsDurationAndCost(): void {
		$user = $this->user('alice');
		$x = $this->client('Client X');
		$y = $this->client('Client Y');
		$a = $this->project('Project A', $x);
		$b = $this->project('Project B', $y);
		$this->entry($user, 'Coding', '2026-02-02 08:00', 600, $a, 100);
		$this->entry($user, 'Review', '2026-02-02 09:00', 300, $b);
		$this->entry($user, 'Coding', '2026-02-02 10:00', 1200, $a, 250);
		$this->entry($user, 'Lunch', '2026-02-02 11:00', 60);

		$this->assertSame(
			[
				['Project A', 'Client X', 1800, 350, 'Coding'],
				['Project B', 'Client Y', 300, 0, 'Review'],
				[null, null, 60, 0, 'Lunch'],
			],
			$this->pick($this->report($user, '', 'project', '', self::BERLIN), 'project', 'client', 'totalDuration', 'cost', 'name')
		);
	}

	public function testGroupByClientListsItsProjects(): void {
		$user = $this->user('alice');
		$x = $this->client('Client X');
		$a = $this->project('Project A', $x);
		$b = $this->project('Project B', $x);
		$this->entry($user, 'One', '2026-02-02 08:00', 100, $a);
		$this->entry($user, 'Two', '2026-02-02 09:00', 200, $b);
		$this->entry($user, 'Three', '2026-02-02 10:00', 300, $a);

		$this->assertSame(
			[['Client X', 'Project A,Project B', 600, 'One,Two,Three']],
			$this->pick($this->report($user, '', 'client', '', self::BERLIN), 'client', 'project', 'totalDuration', 'name')
		);
	}

	public function testGroupByUserReportsAllUsersOnlyForAdmins(): void {
		$alice = $this->user('alice');
		$bob = $this->user('bob');
		$a = $this->project('Project A');
		$this->entry($alice, 'Work', '2026-02-02 08:00', 100, $a);
		$this->entry($bob, 'Work', '2026-02-02 09:00', 200, $a);
		$this->entry($alice, 'Work', '2026-02-02 10:00', 300, $a);

		$this->assertSame(
			[[$alice, 400], [$bob, 200]],
			$this->pick($this->mapper->report($alice, null, null, [$a], [], [], '', 'userUid', '', true, 0, 0, new \DateTimeZone('UTC')), 'userUid', 'totalDuration')
		);
		$this->assertSame(
			[[$alice, 400]],
			$this->pick($this->mapper->report($alice, null, null, [$a], [], [], '', 'userUid', '', false, 0, 0, new \DateTimeZone('UTC')), 'userUid', 'totalDuration')
		);
	}

	public function testTwoLevelGroupingWithTimeGroup(): void {
		$user = $this->user('alice');
		$a = $this->project('Project A');
		$b = $this->project('Project B');
		$this->entry($user, 'Coding', '2026-02-02 08:00', 100, $a);
		$this->entry($user, 'Review', '2026-02-02 09:00', 200, $a);
		$this->entry($user, 'Coding', '2026-02-02 10:00', 300, $b);
		$this->entry($user, 'Coding', '2026-02-03 08:00', 400, $a);

		$this->assertSame(
			[
				['2026-02-02', 'Project A', 'Coding', 100],
				['2026-02-02', 'Project A', 'Review', 200],
				['2026-02-02', 'Project B', 'Coding', 300],
				['2026-02-03', 'Project A', 'Coding', 400],
			],
			$this->pick($this->report($user, 'day', 'project', 'name', self::BERLIN), 'ftime', 'project', 'name', 'totalDuration')
		);
	}

	public function testAggregatedNamesAreDistinctAndShortened(): void {
		$user = $this->user('alice');
		$a = $this->project('Project A');
		foreach (['Alpha task', 'Beta task', 'Alpha task', 'Gamma task', 'Delta task', 'Epsilon'] as $i => $name) {
			$this->entry($user, $name, '2026-02-02 0' . $i . ':00', 60, $a);
		}

		$items = $this->report($user, '', 'project', '', self::BERLIN);
		$this->assertSame('Alpha task,Beta task,Gamma task,Delta ta...', $items[0]->name);
		$this->assertSame(360, $items[0]->totalDuration);

		// grouped by name, names are never shortened or combined
		$this->assertSame(
			['Alpha task', 'Beta task', 'Gamma task', 'Delta task', 'Epsilon'],
			array_column($this->pick($this->report($user, '', 'name', '', self::BERLIN), 'name'), 0)
		);
	}

	// --- without grouping, filters ---

	public function testWithoutGroupingEveryEntryIsOneRow(): void {
		$user = $this->user('alice');
		$a = $this->project('Project A', $this->client('Client X'));
		$this->entry($user, 'Second', '2026-02-02 10:00', 200, $a, 50, 'Some details');
		$this->entry($user, 'First', '2026-02-02 08:00', 100);

		$start = (new \DateTimeImmutable('2026-02-02 08:00', new \DateTimeZone('UTC')))->getTimestamp();
		$this->assertSame(
			[
				['First', '', null, null, (string)$start, 100, null],
				['Second', 'Some details', 'Project A', 'Client X', (string)($start + 7200), 200, 50],
			],
			$this->pick($this->report($user, '', '', '', self::BERLIN), 'name', 'details', 'project', 'client', 'time', 'totalDuration', 'cost')
		);
	}

	public function testProjectFilterCanIncludeEntriesWithoutProject(): void {
		$user = $this->user('alice');
		$a = $this->project('Project A');
		$b = $this->project('Project B');
		$this->entry($user, 'On A', '2026-02-02 08:00', 100, $a);
		$this->entry($user, 'On B', '2026-02-02 09:00', 200, $b);
		$this->entry($user, 'No project', '2026-02-02 10:00', 300);

		$names = fn (array $filter) => array_column($this->pick(
			$this->mapper->report($user, null, null, $filter, [], [], '', '', '', false, 0, 0, new \DateTimeZone('UTC')),
			'name'
		), 0);
		$this->assertSame(['On A'], $names([$a]));
		$this->assertSame(['On A', 'No project'], $names([$a, null]));
		$this->assertSame(['On A', 'On B', 'No project'], $names([]));
	}

	public function testDateRangeExcludesItsBounds(): void {
		$user = $this->user('alice');
		$at = fn (string $utc) => (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->getTimestamp();
		$this->entry($user, 'Before', '2026-02-01 12:00', 100);
		$this->entry($user, 'Inside', '2026-02-02 12:00', 200);
		$this->entry($user, 'At end', '2026-02-03 12:00', 300);

		$items = $this->mapper->report($user, $at('2026-02-01 12:00'), $at('2026-02-03 12:00'), [], [], [], '', '', '', false, 0, 0, new \DateTimeZone('UTC'));
		$this->assertSame(['Inside'], array_column($this->pick($items, 'name'), 0));
	}

	public function testOffsetAndLimitApplyToTheResultingRows(): void {
		$user = $this->user('alice');
		foreach (['2026-02-02', '2026-02-03', '2026-02-04', '2026-02-05'] as $day) {
			$this->entry($user, 'Work', $day . ' 08:00', 100);
			$this->entry($user, 'Work', $day . ' 09:00', 100);
		}
		$items = $this->mapper->report($user, null, null, [], [], [], 'day', '', '', false, 1, 2, new \DateTimeZone('UTC'));
		$this->assertSame([['2026-02-03', 200], ['2026-02-04', 200]], $this->pick($items, 'ftime', 'totalDuration'));
	}

	// --- helpers ---

	/**
	 * @return ReportItem[]
	 */
	private function report(string $user, string $timegroup, string $group1, string $group2, string $timeZone): array {
		return $this->mapper->report($user, null, null, [], [], [], $timegroup, $group1, $group2, false, 0, 0, new \DateTimeZone($timeZone));
	}

	/**
	 * The given fields of each item, as lists.
	 *
	 * @param ReportItem[] $items
	 */
	private function pick(array $items, string ...$fields): array {
		return array_map(fn (ReportItem $item) => array_map(fn ($f) => $item->$f, $fields), $items);
	}

	private function user(string $name): string {
		return $this->prefix . $name;
	}

	private function client(string $name): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('timetracker_client')->values([
			'name' => $qb->createNamedParameter($name),
			'created_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return $this->clientIds[] = $qb->getLastInsertId();
	}

	private function project(string $name, ?int $clientId = null): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('timetracker_project')->values([
			'name' => $qb->createNamedParameter($name),
			'client_id' => $qb->createNamedParameter($clientId, $clientId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'created_by_user_uid' => $qb->createNamedParameter($this->prefix . 'admin'),
			'created_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return $this->projectIds[] = $qb->getLastInsertId();
	}

	/**
	 * @param string $startUtc e.g. "2026-03-10 22:30", in UTC
	 */
	private function entry(string $user, string $name, string $startUtc, int $duration, ?int $projectId = null, ?int $cost = null, string $details = ''): void {
		$start = (new \DateTimeImmutable($startUtc, new \DateTimeZone('UTC')))->getTimestamp();
		$qb = $this->db->getQueryBuilder();
		$qb->insert('timetracker_work_interval')->values([
			'name' => $qb->createNamedParameter($name),
			'details' => $qb->createNamedParameter($details),
			'project_id' => $qb->createNamedParameter($projectId, $projectId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'user_uid' => $qb->createNamedParameter($user),
			'start' => $qb->createNamedParameter($start, IQueryBuilder::PARAM_INT),
			'duration' => $qb->createNamedParameter($duration, IQueryBuilder::PARAM_INT),
			'running' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			'cost' => $qb->createNamedParameter($cost, $cost === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}
}
