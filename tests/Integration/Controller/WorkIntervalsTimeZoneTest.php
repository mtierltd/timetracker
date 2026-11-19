<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCP\L10N\IFactory;
use Test\TestCase;

/**
 * The Timer list doesn't change PHP's default time zone, and its day labels
 * don't depend on it (#339).
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class WorkIntervalsTimeZoneTest extends TestCase {
	use CreatesAjaxController;

	/** @var string */
	private $user;
	/** @var string */
	private $defaultTimeZone;
	/** @var int[] */
	private $created = [];

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'tt-tz-' . bin2hex(random_bytes(4));
		$this->defaultTimeZone = date_default_timezone_get();
	}

	protected function tearDown(): void {
		date_default_timezone_set($this->defaultTimeZone);
		$mapper = \OCP\Server::get(WorkIntervalMapper::class);
		foreach ($this->created as $id) {
			$mapper->delete($mapper->find($id));
		}
		parent::tearDown();
	}

	public function testDayLabelsIgnoreAndKeepTheDefaultTimeZone(): void {
		// 2026-07-20 22:30 UTC is 00:30 on 21 July in Berlin (summer time, UTC+2)
		$start = gmmktime(22, 30, 0, 7, 20, 2026);
		$wi = new WorkInterval();
		$wi->setUserUid($this->user);
		$wi->setName('Late entry');
		$wi->setStart($start);
		$wi->setDuration(1800);
		$wi->setRunning(0);
		$wi = \OCP\Server::get(WorkIntervalMapper::class)->insert($wi);
		$this->created[] = $wi->getId();

		// another component changed PHP's default time zone earlier in the request
		date_default_timezone_set('Pacific/Kiritimati'); // UTC+14

		$data = $this->ajaxController($this->user, [
			'from' => $start - 3600,
			'to' => $start + 3600,
			'tzoffset' => -120, // the browser in Berlin, as Date.getTimezoneOffset()
		])->workIntervals()->getData();

		$this->assertSame('Pacific/Kiritimati', date_default_timezone_get(), 'workIntervals() changed PHP\'s default time zone');

		$l10n = \OCP\Server::get(IFactory::class)->get('timetracker');
		$expected = $l10n->l('date', new \DateTime('@' . ($start + 7200)), ['width' => 'medium']); // 21 July
		$this->assertSame([$expected], array_keys($data['days']));
		$this->assertStringContainsString('21', $expected);
	}
}
