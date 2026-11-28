<?php

namespace OCA\TimeTracker\Tests\Unit\Service;

use OCA\TimeTracker\Service\AbsenceDays;
use OCA\TimeTracker\Service\WorkModel;
use PHPUnit\Framework\TestCase;

class AbsenceDaysTest extends TestCase {

	public function testWeekendsAndHolidaysDontCount(): void {
		// Mon 30 March to Fri 10 April 2026: Good Friday (3 April) and Easter Monday (6 April) are holidays
		$days = AbsenceDays::workingDays('2026-03-30', '2026-04-10', false, false, new WorkModel(), '');
		$this->assertSame(8.0, array_sum($days));
		$this->assertArrayNotHasKey('2026-04-03', $days);
		$this->assertArrayNotHasKey('2026-04-04', $days);
	}

	public function testHalfDays(): void {
		$days = AbsenceDays::workingDays('2026-07-06', '2026-07-10', true, true, new WorkModel(), '');
		$this->assertSame(['2026-07-06' => 0.5, '2026-07-07' => 1.0, '2026-07-08' => 1.0, '2026-07-09' => 1.0, '2026-07-10' => 0.5], $days);
	}

	public function testStateHolidays(): void {
		// Fronleichnam, 4 June 2026, is a holiday in Bavaria but not in Berlin
		$this->assertSame([], AbsenceDays::workingDays('2026-06-04', '2026-06-04', false, false, new WorkModel(), 'BY'));
		$this->assertSame(['2026-06-04' => 1.0], AbsenceDays::workingDays('2026-06-04', '2026-06-04', false, false, new WorkModel(), 'BE'));
	}

	public function testPartTimeModel(): void {
		// works Monday to Wednesday only
		$model = new WorkModel([1 => 480, 2 => 480, 3 => 240]);
		$this->assertSame(3.0, array_sum(AbsenceDays::workingDays('2026-07-06', '2026-07-12', false, false, $model, '')));
	}

	public function testAcrossTheYear(): void {
		// 31 Dec 2026 (Thu) to 4 Jan 2027 (Mon): New Year's Day is a holiday
		$this->assertSame(['2026-12-31' => 1.0, '2027-01-04' => 1.0], AbsenceDays::workingDays('2026-12-31', '2027-01-04', false, false, new WorkModel(), ''));
	}

	public function testIsDate(): void {
		$this->assertTrue(AbsenceDays::isDate('2028-02-29'));
		$this->assertFalse(AbsenceDays::isDate('2026-02-29'));
		$this->assertFalse(AbsenceDays::isDate('2026-1-01'));
		$this->assertFalse(AbsenceDays::isDate(20260101));
	}
}
