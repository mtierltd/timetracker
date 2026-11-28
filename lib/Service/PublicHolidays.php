<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

/**
 * Statutory public holidays in Germany, per federal state, calculated
 * locally (no external service).
 *
 * Holidays that only apply in parts of a state (Mariä Himmelfahrt in
 * Catholic communities of Bavaria, Augsburger Friedensfest, Fronleichnam in
 * parts of Saxony and Thuringia) aren't included; admins can't add them yet.
 */
class PublicHolidays {

	/** Federal state codes (ISO 3166-2:DE without "DE-") and their names. */
	public const STATES = [
		'BW' => 'Baden-Württemberg',
		'BY' => 'Bayern',
		'BE' => 'Berlin',
		'BB' => 'Brandenburg',
		'HB' => 'Bremen',
		'HH' => 'Hamburg',
		'HE' => 'Hessen',
		'MV' => 'Mecklenburg-Vorpommern',
		'NI' => 'Niedersachsen',
		'NW' => 'Nordrhein-Westfalen',
		'RP' => 'Rheinland-Pfalz',
		'SL' => 'Saarland',
		'SN' => 'Sachsen',
		'ST' => 'Sachsen-Anhalt',
		'SH' => 'Schleswig-Holstein',
		'TH' => 'Thüringen',
	];

	/**
	 * The holidays of a year in a state, sorted by date.
	 *
	 * @param string $state a key of STATES, or '' for the nationwide holidays only
	 * @return array<string, string> date (Y-m-d) => name
	 */
	public static function forYear(int $year, string $state): array {
		$easter = self::easterSunday($year);
		$e = fn (int $days): string => $easter->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
		$d = fn (int $month, int $day): string => sprintf('%04d-%02d-%02d', $year, $month, $day);

		$holidays = [
			$d(1, 1) => 'Neujahr',
			$e(-2) => 'Karfreitag',
			$e(1) => 'Ostermontag',
			$d(5, 1) => 'Tag der Arbeit',
			$e(39) => 'Christi Himmelfahrt',
			$e(50) => 'Pfingstmontag',
			$d(10, 3) => 'Tag der Deutschen Einheit',
			$d(12, 25) => '1. Weihnachtstag',
			$d(12, 26) => '2. Weihnachtstag',
		];

		$in = fn (string ...$states): bool => in_array($state, $states, true);
		if ($in('BW', 'BY', 'ST')) {
			$holidays[$d(1, 6)] = 'Heilige Drei Könige';
		}
		if ($in('BE') && $year >= 2019 || $in('MV') && $year >= 2023) {
			$holidays[$d(3, 8)] = 'Internationaler Frauentag';
		}
		if ($in('BB')) {
			$holidays[$e(0)] = 'Ostersonntag';
			$holidays[$e(49)] = 'Pfingstsonntag';
		}
		if ($in('BW', 'BY', 'HE', 'NW', 'RP', 'SL')) {
			$holidays[$e(60)] = 'Fronleichnam';
		}
		if ($in('SL')) {
			$holidays[$d(8, 15)] = 'Mariä Himmelfahrt';
		}
		if ($in('TH') && $year >= 2019) {
			$holidays[$d(9, 20)] = 'Weltkindertag';
		}
		if ($in('BB', 'MV', 'SN', 'ST', 'TH') || $in('HB', 'HH', 'NI', 'SH') && $year >= 2018) {
			$holidays[$d(10, 31)] = 'Reformationstag';
		}
		if ($in('BW', 'BY', 'NW', 'RP', 'SL')) {
			$holidays[$d(11, 1)] = 'Allerheiligen';
		}
		if ($in('SN')) {
			// the Wednesday before 23 November
			$holidays[(new \DateTimeImmutable($d(11, 23)))->modify('last wednesday')->format('Y-m-d')] = 'Buß- und Bettag';
		}
		ksort($holidays);
		return $holidays;
	}

	/**
	 * Easter Sunday (Gregorian calendar), without the calendar extension.
	 * Anonymous Gregorian algorithm (Meeus/Jones/Butcher).
	 */
	public static function easterSunday(int $year): \DateTimeImmutable {
		$a = $year % 19;
		$b = intdiv($year, 100);
		$c = $year % 100;
		$d = intdiv($b, 4);
		$e = $b % 4;
		$f = intdiv($b + 8, 25);
		$g = intdiv($b - $f + 1, 3);
		$h = (19 * $a + $b - $d - $g + 15) % 30;
		$i = intdiv($c, 4);
		$k = $c % 4;
		$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
		$m = intdiv($a + 11 * $h + 22 * $l, 451);
		$month = intdiv($h + $l - 7 * $m + 114, 31);
		$day = (($h + $l - 7 * $m + 114) % 31) + 1;
		return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new \DateTimeZone('UTC'));
	}
}
