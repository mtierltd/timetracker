<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Db\TimelineEntryMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * Timeline periods and report dates use the browser's time zone (parameter
 * "timezone"), not UTC (#323).
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class TimelinePeriodTest extends TestCase {
	use CreatesAjaxController;

	/** @var string */
	private $user;

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'tt-period-' . bin2hex(random_bytes(4));
	}

	protected function tearDown(): void {
		$db = \OCP\Server::get(IDBConnection::class);
		foreach (['timetracker_work_interval', 'timetracker_timeline_entry', 'timetracker_timeline'] as $table) {
			$qb = $db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($this->user, IQueryBuilder::PARAM_STR)))
				->executeStatement();
		}
		parent::tearDown();
	}

	/** Unix timestamp of a local time in a time zone. */
	private static function localTime(string $local, string $timeZone): int {
		return (new \DateTimeImmutable($local, new \DateTimeZone($timeZone)))->getTimestamp();
	}

	private function timeline(int $from, int $to, ?string $timeZone, string $timegroup = 'day'): array {
		$params = ['name' => '', 'from' => $from, 'to' => $to, 'group1' => 'project', 'group2' => '', 'timegroup' => $timegroup];
		if ($timeZone !== null) {
			$params['timezone'] = $timeZone;
		}
		return $this->ajaxController($this->user, $params)->postTimeline()->getData()['Timeline'];
	}

	public function testPeriodEastOfUtc(): void {
		// Berlin: 1 November 00:00 is still 31 October in UTC
		$timeline = $this->timeline(self::localTime('2026-11-01 00:00:00', 'Europe/Berlin'), self::localTime('2026-11-17 23:59:59', 'Europe/Berlin'), 'Europe/Berlin');
		$this->assertSame('November 1, 2026 - November 17, 2026', $timeline['timeInterval']);
	}

	public function testPeriodWestOfUtc(): void {
		// Los Angeles: 18 November 23:59 is already 19 November in UTC
		$timeline = $this->timeline(self::localTime('2026-11-01 00:00:00', 'America/Los_Angeles'), self::localTime('2026-11-18 23:59:59', 'America/Los_Angeles'), 'America/Los_Angeles');
		$this->assertSame('November 1, 2026 - November 18, 2026', $timeline['timeInterval']);
	}

	public function testEntriesAreGroupedByTheBrowsersDays(): void {
		// 23:30 on 17 November in Los Angeles is 07:30 on 18 November in UTC
		$start = self::localTime('2026-11-17 23:30:00', 'America/Los_Angeles');
		$wi = new WorkInterval();
		$wi->setUserUid($this->user);
		$wi->setName('Late work');
		$wi->setStart($start);
		$wi->setDuration(600);
		$wi->setRunning(0);
		\OCP\Server::get(WorkIntervalMapper::class)->insert($wi);

		$from = self::localTime('2026-11-17 00:00:00', 'America/Los_Angeles');
		$to = self::localTime('2026-11-18 23:59:59', 'America/Los_Angeles');

		$report = $this->ajaxController($this->user, ['name' => '', 'from' => $from, 'to' => $to, 'group1' => 'project', 'group2' => '',
			'timegroup' => 'day', 'timezone' => 'America/Los_Angeles'])->getReport()->getData()['items'];
		$this->assertSame(['2026-11-17'], array_column($report, 'ftime'));

		$timeline = $this->timeline($from, $to, 'America/Los_Angeles');
		$entries = \OCP\Server::get(TimelineEntryMapper::class)->findTimelineEntries($timeline['id']);
		$this->assertSame(['2026-11-17'], array_map(fn ($e) => $e->getTimeInterval(), $entries));

		// without time grouping: the date of the earliest entry, not its timestamp
		$timeline = $this->timeline($from, $to, 'America/Los_Angeles', '');
		$entries = \OCP\Server::get(TimelineEntryMapper::class)->findTimelineEntries($timeline['id']);
		$this->assertSame(['November 17, 2026'], array_map(fn ($e) => $e->getTimeInterval(), $entries));
	}

	public function testInvalidTimeZoneFallsBackToTheUserSetting(): void {
		// no Nextcloud time zone for this user: the server default applies
		$from = self::localTime('2026-11-01 12:00:00', 'UTC');
		$to = self::localTime('2026-11-17 12:00:00', 'UTC');
		$timeline = $this->timeline($from, $to, 'Mars/Olympus_Mons');
		$this->assertSame($this->timeline($from, $to, null)['timeInterval'], $timeline['timeInterval']);
		$this->assertStringContainsString('November 1, 2026', $timeline['timeInterval']);
	}
}
