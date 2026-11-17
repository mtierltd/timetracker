<?php

namespace OCA\TimeTracker\Tests\Unit\Controller;

use OCA\TimeTracker\Controller\AjaxController;
use PHPUnit\Framework\TestCase;

/**
 * Manual entries and edited times arrive as local "d/m/y H:i" plus the
 * browser's UTC offset on those dates (#151).
 */
class LocalTimesToIntervalTest extends TestCase {

	public function testSummerDateUsesItsOwnOffset(): void {
		// Berlin, 20 July 2026, 10:00-11:00 local = 08:00-09:00 UTC (offset -120)
		[$start, $duration] = AjaxController::localTimesToInterval('20/07/26 10:00', '20/07/26 11:00', -120, -120);
		$this->assertSame(gmmktime(8, 0, 0, 7, 20, 2026), $start);
		$this->assertSame(3600, $duration);
	}

	public function testWinterDate(): void {
		// Berlin, 20 January 2026, 10:00-11:30 local = 09:00-10:30 UTC (offset -60)
		[$start, $duration] = AjaxController::localTimesToInterval('20/01/26 10:00', '20/01/26 11:30', -60, -60);
		$this->assertSame(gmmktime(9, 0, 0, 1, 20, 2026), $start);
		$this->assertSame(5400, $duration);
	}

	public function testEntryAcrossTheClockChange(): void {
		// Berlin, night of 25 October 2026: 01:00 (summer, -120) to 04:00 (winter, -60) is 4 real hours
		[$start, $duration] = AjaxController::localTimesToInterval('25/10/26 01:00', '25/10/26 04:00', -120, -60);
		$this->assertSame(gmmktime(23, 0, 0, 10, 24, 2026), $start);
		$this->assertSame(4 * 3600, $duration);
	}

	public function testOneOffsetForBothTimes(): void {
		// older clients send only tzoffset
		[$start, $duration] = AjaxController::localTimesToInterval('20/07/26 10:00', '20/07/26 11:00', 300, null);
		$this->assertSame(gmmktime(15, 0, 0, 7, 20, 2026), $start);
		$this->assertSame(3600, $duration);
	}
}
