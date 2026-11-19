<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Db\TimelineEntryMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * Reports and timelines include all rows, not only the first 1,000 (#336).
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class ReportLimitTest extends TestCase {
	use CreatesAjaxController;

	private const ENTRIES = 1050; // more than the old limit of 1,000
	private const DURATION = 600;

	/** @var string */
	private $user;
	/** @var int */
	private $first;

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'tt-report-' . bin2hex(random_bytes(4));
		$this->first = gmmktime(8, 0, 0, 1, 5, 2026);

		$db = \OCP\Server::get(IDBConnection::class);
		$mapper = \OCP\Server::get(WorkIntervalMapper::class);
		$db->beginTransaction();
		for ($i = 0; $i < self::ENTRIES; $i++) {
			$wi = new WorkInterval();
			$wi->setUserUid($this->user);
			$wi->setName('Entry ' . $i);
			$wi->setStart($this->first + $i * 900);
			$wi->setDuration(self::DURATION);
			$wi->setRunning(0);
			$mapper->insert($wi);
		}
		$db->commit();
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

	private function params(): array {
		return [
			'name' => '',
			'from' => $this->first - 1,
			'to' => $this->first + self::ENTRIES * 900,
			'group1' => '',
			'group2' => '',
			'timegroup' => '',
		];
	}

	public function testReportReturnsAllRows(): void {
		$data = $this->ajaxController($this->user, $this->params())->getReport()->getData();

		$this->assertCount(self::ENTRIES, $data['items']);
		$this->assertSame(self::ENTRIES, $data['total']);
		$this->assertSame(self::ENTRIES * self::DURATION, array_sum(array_column($data['items'], 'totalDuration')));
	}

	public function testTimelineIncludesAllRows(): void {
		$timeline = $this->ajaxController($this->user, $this->params())->postTimeline()->getData()['Timeline'];

		$this->assertEquals(self::ENTRIES * self::DURATION, $timeline['totalDuration']);
		$entries = \OCP\Server::get(TimelineEntryMapper::class)->findTimelineEntries($timeline['id']);
		$this->assertCount(self::ENTRIES, $entries);
	}
}
