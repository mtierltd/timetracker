<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

/**
 * Which working days an absence covers. The days are calculated from the
 * work model and the public holidays every time and never stored, so they
 * stay correct when the model or the holidays change.
 */
final class AbsenceDays {

	/** @var array<string, array<string, string>> "year|state" => holidays */
	private static array $holidayCache = [];

	/**
	 * The working days between two dates (both included), with the share of
	 * the day the absence covers: 1, or 0.5 for a half day at the start or end.
	 * Days without target minutes and public holidays aren't included.
	 *
	 * @param string $start Y-m-d
	 * @param string $end Y-m-d, not before $start
	 * @return array<string, float> Y-m-d => 1.0 or 0.5
	 */
	public static function workingDays(string $start, string $end, bool $halfDayStart, bool $halfDayEnd, WorkModel $model, string $state): array {
		$days = [];
		foreach (self::dates($start, $end) as $date) {
			$key = $date->format('Y-m-d');
			if (!$model->isWorkingDay($date) || self::isHoliday($key, $state)) {
				continue;
			}
			$share = 1.0;
			if (($key === $start && $halfDayStart) || ($key === $end && $halfDayEnd)) {
				$share = 0.5;
			}
			$days[$key] = $share;
		}
		return $days;
	}

	public static function isHoliday(string $date, string $state): bool {
		return self::holidayName($date, $state) !== null;
	}

	public static function holidayName(string $date, string $state): ?string {
		$cacheKey = substr($date, 0, 4) . '|' . $state;
		if (!isset(self::$holidayCache[$cacheKey])) {
			self::$holidayCache[$cacheKey] = PublicHolidays::forYear((int)substr($date, 0, 4), $state);
		}
		return self::$holidayCache[$cacheKey][$date] ?? null;
	}

	/**
	 * Every date from $start to $end (both included).
	 *
	 * @return \Generator<\DateTimeImmutable>
	 */
	public static function dates(string $start, string $end): \Generator {
		$utc = new \DateTimeZone('UTC');
		$date = new \DateTimeImmutable($start, $utc);
		$last = new \DateTimeImmutable($end, $utc);
		while ($date <= $last) {
			yield $date;
			$date = $date->modify('+1 day');
		}
	}

	/** Whether a string is a valid Y-m-d date. */
	public static function isDate(mixed $value): bool {
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return false;
		}
		[$y, $m, $d] = array_map('intval', explode('-', $value));
		return checkdate($m, $d, $y);
	}
}
