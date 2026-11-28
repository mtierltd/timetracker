<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Recorded working time per day, in the user's time zone (#376). Entries
 * across midnight are split between the days; a running entry counts until
 * now.
 */
class WorkedTime {

	public function __construct(
		private IDBConnection $db,
		private UserTimeZone $userTimeZone,
	) {
	}

	/**
	 * @param string $from Y-m-d
	 * @param string $to Y-m-d, both included
	 * @return array<string, int> Y-m-d => minutes
	 */
	public function minutesPerDay(string $userId, string $from, string $to, ?int $now = null): array {
		$tz = $this->userTimeZone->get($userId);
		$rangeStart = (new \DateTimeImmutable($from, $tz))->getTimestamp();
		$rangeEnd = (new \DateTimeImmutable($to, $tz))->modify('+1 day')->getTimestamp();
		$now ??= time();

		$qb = $this->db->getQueryBuilder();
		$qb->select('start', 'duration', 'running')
			->from('timetracker_work_interval')
			->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->lt('start', $qb->createNamedParameter($rangeEnd, IQueryBuilder::PARAM_INT)))
			// entries are at most a few days long; this also finds those that started before the range
			->andWhere($qb->expr()->gte('start', $qb->createNamedParameter($rangeStart - 7 * 86400, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$intervals = [];
		while ($row = $result->fetch()) {
			$start = (int)$row['start'];
			$end = (int)$row['running'] ? $now : $start + (int)$row['duration'];
			$intervals[] = [$start, $end];
		}
		$result->closeCursor();
		return self::split($intervals, $rangeStart, $rangeEnd, $tz);
	}

	/**
	 * Splits intervals into minutes per local day, within the range.
	 *
	 * @param list<array{int, int}> $intervals [start, end] timestamps
	 * @return array<string, int> Y-m-d => minutes
	 */
	public static function split(array $intervals, int $rangeStart, int $rangeEnd, \DateTimeZone $tz): array {
		$seconds = [];
		foreach ($intervals as [$start, $end]) {
			$start = max($start, $rangeStart);
			$end = min($end, $rangeEnd);
			while ($start < $end) {
				$day = (new \DateTimeImmutable('@' . $start))->setTimezone($tz);
				$nextMidnight = $day->setTime(0, 0)->modify('+1 day')->getTimestamp();
				$partEnd = min($end, $nextMidnight);
				$key = $day->format('Y-m-d');
				$seconds[$key] = ($seconds[$key] ?? 0) + $partEnd - $start;
				$start = $partEnd;
			}
		}
		ksort($seconds);
		return array_map(fn ($s) => intdiv($s + 30, 60), $seconds);
	}
}
