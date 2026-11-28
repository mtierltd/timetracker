<?php

namespace OCA\TimeTracker\Tests\Unit\Service;

use OCA\TimeTracker\Service\AbsenceService;
use OCA\TimeTracker\Service\VacationBalance;
use PHPUnit\Framework\TestCase;

class VacationBalanceTest extends TestCase {

	public function testRemainingDays(): void {
		$balance = VacationBalance::calculate(30, 0, null,
			['2026-07-06' => 1.0, '2026-07-07' => 1.0], ['2026-08-03' => 0.5], '2026-06-01');
		$this->assertSame(2.0, $balance['approved']);
		$this->assertSame(0.5, $balance['requested']);
		$this->assertSame(27.5, $balance['remaining']);
	}

	public function testIllnessGivesVacationDaysBack(): void {
		$vacation = ['2026-07-06' => 1.0, '2026-07-07' => 1.0, '2026-07-08' => 1.0];
		$illness = ['2026-07-07' => 1.0, '2026-07-08' => 0.5];
		$this->assertSame(['2026-07-06' => 1.0, '2026-07-08' => 0.5], VacationBalance::withoutOtherAbsences($vacation, $illness));
	}

	public function testCarriedOverDaysExpireIfNotTaken(): void {
		// 5 carried over until 31 March, 2 taken by then: 3 expire after it
		$taken = ['2026-02-02' => 1.0, '2026-03-30' => 1.0, '2026-07-06' => 1.0];
		$before = VacationBalance::calculate(30, 5, '2026-03-31', $taken, [], '2026-03-31');
		$this->assertSame(0.0, $before['carriedOverExpired']);
		$this->assertSame(32.0, $before['remaining']);

		$after = VacationBalance::calculate(30, 5, '2026-03-31', $taken, [], '2026-04-01');
		$this->assertSame(3.0, $after['carriedOverExpired']);
		$this->assertSame(29.0, $after['remaining']);
	}

	public function testCarriedOverDaysAllTakenDontExpire(): void {
		$taken = ['2026-01-05' => 1.0, '2026-01-06' => 1.0, '2026-01-07' => 1.0];
		$this->assertSame(0.0, VacationBalance::calculate(30, 2, '2026-03-31', $taken, [], '2026-12-01')['carriedOverExpired']);
	}

	public function testToHalfDays(): void {
		$this->assertSame(25, AbsenceService::toHalfDays('12.5'));
		$this->assertSame(60, AbsenceService::toHalfDays(30));
		$this->assertNull(AbsenceService::toHalfDays('12.3'));
		$this->assertNull(AbsenceService::toHalfDays(-1));
		$this->assertNull(AbsenceService::toHalfDays('abc'));
	}
}
