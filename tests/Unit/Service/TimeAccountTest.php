<?php

namespace OCA\TimeTracker\Tests\Unit\Service;

use OCA\TimeTracker\Service\TimeAccount;
use OCA\TimeTracker\Service\WorkedTime;
use OCA\TimeTracker\Service\WorkModel;
use PHPUnit\Framework\TestCase;

class TimeAccountTest extends TestCase {

	public function testTargetActualAndBalance(): void {
		// Mon 4 to Sun 10 May 2026, 8 h Mon-Fri. Fri 1 May is outside the range.
		$account = TimeAccount::calculate('2026-05-04', '2026-05-10', new WorkModel(), '',
			['2026-05-05' => 1.0, '2026-05-06' => 0.5], // vacation Tuesday, half day Wednesday
			['2026-05-07' => 1.0], // business trip Thursday
			['2026-05-04' => 540, '2026-05-06' => 240, '2026-05-08' => 420, '2026-05-09' => 60]);
		$byDate = array_column($account['days'], null, 'date');
		$this->assertSame(480, $byDate['2026-05-04']['target']);
		$this->assertSame(0, $byDate['2026-05-05']['target']);
		$this->assertSame(240, $byDate['2026-05-06']['target']);
		$this->assertSame(480, $byDate['2026-05-07']['actual']);
		$this->assertSame(60, $byDate['2026-05-09']['actual']);
		$this->assertSame(480 + 240 + 480 + 480, $account['target']);
		$this->assertSame(540 + 240 + 480 + 420 + 60, $account['actual']);
		$this->assertSame(60, $account['balance']);
	}

	public function testHolidaysHaveNoTarget(): void {
		// Ascension Day, Thu 14 May 2026
		$account = TimeAccount::calculate('2026-05-14', '2026-05-14', new WorkModel(), 'BY', [], [], ['2026-05-14' => 120]);
		$this->assertSame('Christi Himmelfahrt', $account['days'][0]['holiday']);
		$this->assertSame(0, $account['target']);
		$this->assertSame(120, $account['balance']);
	}

	public function testPartTime(): void {
		$model = new WorkModel([1 => 360, 2 => 360, 3 => 360]);
		$this->assertSame(3 * 360, TimeAccount::calculate('2026-05-04', '2026-05-10', $model, '', [], [], [])['target']);
	}

	public function testWorkedTimeIsSplitAtLocalMidnight(): void {
		$tz = new \DateTimeZone('Europe/Berlin');
		// 22:00 to 02:00 local time, 6/7 July 2026 (UTC+2)
		$start = (new \DateTimeImmutable('2026-07-06 22:00', $tz))->getTimestamp();
		$rangeStart = (new \DateTimeImmutable('2026-07-01', $tz))->getTimestamp();
		$rangeEnd = (new \DateTimeImmutable('2026-08-01', $tz))->getTimestamp();
		$this->assertSame(['2026-07-06' => 120, '2026-07-07' => 120],
			WorkedTime::split([[$start, $start + 4 * 3600]], $rangeStart, $rangeEnd, $tz));
	}

	public function testWorkedTimeOutsideTheRangeIsCut(): void {
		$tz = new \DateTimeZone('UTC');
		$rangeStart = gmmktime(0, 0, 0, 7, 1, 2026);
		$this->assertSame(['2026-07-01' => 60],
			WorkedTime::split([[$rangeStart - 3600, $rangeStart + 3600]], $rangeStart, $rangeStart + 86400, $tz));
	}
}
