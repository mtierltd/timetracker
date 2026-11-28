<?php

namespace OCA\TimeTracker\Tests\Unit\Service;

use OCA\TimeTracker\Service\GoalTargets;
use PHPUnit\Framework\TestCase;

class GoalTargetsTest extends TestCase {

	// week of Monday 23 November 2026
	private const MON = '2026-11-23';
	private const SUN = '2026-11-29';

	public function testNothingSkipped(): void {
		$this->assertSame(['target' => 40.0, 'countedDays' => 5, 'skippedDays' => 0],
			GoalTargets::forPeriod(40, '1111100', self::MON, self::SUN, []));
	}

	public function testTwoWorkingDaysSkipped(): void {
		$this->assertSame(24.0, GoalTargets::forPeriod(40, '1111100', self::MON, self::SUN, [['2026-11-26', '2026-11-27']])['target']);
	}

	public function testSkippedWeekendDoesntReduceAWorkGoal(): void {
		$this->assertSame(40.0, GoalTargets::forPeriod(40, '1111100', self::MON, self::SUN, [['2026-11-28', '2026-11-29']])['target']);
	}

	public function testEveryDayGoal(): void {
		// 7 hours a week, every day: skipping the weekend leaves 5 hours
		$this->assertSame(5.0, GoalTargets::forPeriod(7, '1111111', self::MON, self::SUN, [['2026-11-28', '2026-11-29']])['target']);
	}

	public function testWholePeriodSkipped(): void {
		$this->assertSame(0.0, GoalTargets::forPeriod(40, '1111100', self::MON, self::SUN, [['2026-11-20', '2026-12-04']])['target']);
	}

	public function testOverlappingSkipsCountOnce(): void {
		$result = GoalTargets::forPeriod(40, '1111100', self::MON, self::SUN, [['2026-11-23', '2026-11-24'], ['2026-11-24', '2026-11-24']]);
		$this->assertSame(2, $result['skippedDays']);
		$this->assertSame(24.0, $result['target']);
	}

	public function testMonth(): void {
		// November 2026 has 21 weekdays; 1 skipped
		$result = GoalTargets::forPeriod(84, '1111100', '2026-11-01', '2026-11-30', [['2026-11-02', '2026-11-02']]);
		$this->assertSame(21, $result['countedDays']);
		$this->assertSame(80.0, $result['target']);
	}

	public function testPeriodRange(): void {
		$this->assertSame(['2026-11-23', '2026-11-29'], GoalTargets::periodRange('2026-11-23'));
		$this->assertSame(['2026-02-01', '2026-02-28'], GoalTargets::periodRange('2026-02'));
	}

	public function testValidDays(): void {
		$this->assertTrue(GoalTargets::isValidDays('1111100'));
		$this->assertFalse(GoalTargets::isValidDays('0000000'));
		$this->assertFalse(GoalTargets::isValidDays('11111'));
		$this->assertFalse(GoalTargets::isValidDays(null));
	}
}
