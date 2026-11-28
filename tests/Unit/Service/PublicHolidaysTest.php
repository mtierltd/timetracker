<?php

namespace OCA\TimeTracker\Tests\Unit\Service;

use OCA\TimeTracker\Service\PublicHolidays;
use PHPUnit\Framework\TestCase;

class PublicHolidaysTest extends TestCase {

	public function testEasterSunday(): void {
		$this->assertSame('2024-03-31', PublicHolidays::easterSunday(2024)->format('Y-m-d'));
		$this->assertSame('2025-04-20', PublicHolidays::easterSunday(2025)->format('Y-m-d'));
		$this->assertSame('2026-04-05', PublicHolidays::easterSunday(2026)->format('Y-m-d'));
		$this->assertSame('2038-04-25', PublicHolidays::easterSunday(2038)->format('Y-m-d'));
	}

	public function testNationwideHolidays2026(): void {
		$this->assertSame([
			'2026-01-01' => 'Neujahr',
			'2026-04-03' => 'Karfreitag',
			'2026-04-06' => 'Ostermontag',
			'2026-05-01' => 'Tag der Arbeit',
			'2026-05-14' => 'Christi Himmelfahrt',
			'2026-05-25' => 'Pfingstmontag',
			'2026-10-03' => 'Tag der Deutschen Einheit',
			'2026-12-25' => '1. Weihnachtstag',
			'2026-12-26' => '2. Weihnachtstag',
		], PublicHolidays::forYear(2026, ''));
	}

	public function testBavaria(): void {
		$holidays = PublicHolidays::forYear(2026, 'BY');
		$this->assertSame('Heilige Drei Könige', $holidays['2026-01-06']);
		$this->assertSame('Fronleichnam', $holidays['2026-06-04']);
		$this->assertSame('Allerheiligen', $holidays['2026-11-01']);
		$this->assertArrayNotHasKey('2026-10-31', $holidays);
		$this->assertCount(12, $holidays);
	}

	public function testBussUndBettagInSaxony(): void {
		$this->assertSame('Buß- und Bettag', PublicHolidays::forYear(2026, 'SN')['2026-11-18']);
		// 23 November 2022 was a Wednesday: the holiday is the week before
		$this->assertSame('Buß- und Bettag', PublicHolidays::forYear(2022, 'SN')['2022-11-16']);
	}

	public function testHolidaysIntroducedLater(): void {
		$this->assertArrayHasKey('2026-03-08', PublicHolidays::forYear(2026, 'MV'));
		$this->assertArrayNotHasKey('2022-03-08', PublicHolidays::forYear(2022, 'MV'));
		$this->assertArrayHasKey('2026-10-31', PublicHolidays::forYear(2026, 'NI'));
		$this->assertArrayNotHasKey('2017-10-31', PublicHolidays::forYear(2016, 'NI'));
		$this->assertArrayHasKey('2026-09-20', PublicHolidays::forYear(2026, 'TH'));
	}

	public function testEveryStateHasAtLeastTheNationwideHolidays(): void {
		$nationwide = PublicHolidays::forYear(2026, '');
		foreach (array_keys(PublicHolidays::STATES) as $state) {
			$this->assertSame([], array_diff_key($nationwide, PublicHolidays::forYear(2026, $state)), $state);
		}
	}
}
