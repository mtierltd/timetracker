<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

/**
 * The target of a goal in a week or month, reduced by skipped days (#379).
 *
 * A goal counts on certain weekdays (e.g. Monday to Friday for work, every
 * day for language learning). Its target is spread evenly over the counted
 * days of the period; skipped days don't count:
 *
 *   target = hours × (counted days − skipped counted days) / counted days
 *
 * E.g. 40 hours a week, Monday to Friday, 2 days skipped: 24 hours.
 */
final class GoalTargets {

	public const DEFAULT_DAYS = '1111100';

	/** Whether a weekday string is valid: 7 characters 0/1 (Monday first), at least one day. */
	public static function isValidDays(mixed $days): bool {
		return is_string($days) && preg_match('/^[01]{7}$/', $days) === 1 && str_contains($days, '1');
	}

	/**
	 * @param string $days 7 characters 0/1, Monday first
	 * @param string $from Y-m-d, first day of the period
	 * @param string $to Y-m-d, last day of the period
	 * @param list<array{string, string}> $skips skipped date ranges [from, to] (Y-m-d, both included)
	 * @return array{target: float, countedDays: int, skippedDays: int}
	 */
	public static function forPeriod(float $hours, string $days, string $from, string $to, array $skips): array {
		if (!self::isValidDays($days)) {
			$days = self::DEFAULT_DAYS;
		}
		$counted = 0;
		$skipped = 0;
		$utc = new \DateTimeZone('UTC');
		$date = new \DateTimeImmutable($from, $utc);
		$last = new \DateTimeImmutable($to, $utc);
		while ($date <= $last) {
			if ($days[(int)$date->format('N') - 1] === '1') {
				$counted++;
				$key = $date->format('Y-m-d');
				foreach ($skips as [$skipFrom, $skipTo]) {
					if ($key >= $skipFrom && $key <= $skipTo) {
						$skipped++;
						break;
					}
				}
			}
			$date = $date->modify('+1 day');
		}
		$target = $counted === 0 ? 0.0 : $hours * ($counted - $skipped) / $counted;
		return ['target' => $target, 'countedDays' => $counted, 'skippedDays' => $skipped];
	}

	/**
	 * The first and last day of a goal period: a week (key: its Monday,
	 * Y-m-d) or a month (key: Y-m).
	 *
	 * @return array{string, string}
	 */
	public static function periodRange(string $period): array {
		if (strlen($period) === 7) {
			$first = new \DateTimeImmutable($period . '-01', new \DateTimeZone('UTC'));
			return [$first->format('Y-m-d'), $first->format('Y-m-t')];
		}
		$monday = new \DateTimeImmutable($period, new \DateTimeZone('UTC'));
		return [$period, $monday->modify('+6 days')->format('Y-m-d')];
	}
}
