<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

/**
 * Target and actual working time per day (#376). Pure calculation:
 *
 *   target(day) = model minutes(weekday) − public holiday − absences that reduce the target
 *   actual(day) = recorded work + absences that count as working time
 *   balance     = Σ actual − Σ target
 */
final class TimeAccount {

	/**
	 * @param string $from Y-m-d
	 * @param string $to Y-m-d, both included
	 * @param array<string, float> $reducingAbsences Y-m-d => share of the day, of approved absences that reduce the target
	 * @param array<string, float> $workTimeAbsences Y-m-d => share of the day, of approved absences that count as working time
	 * @param array<string, int> $workedMinutes Y-m-d => recorded minutes
	 * @return array{days: list<array{date: string, holiday: ?string, model: int, target: int, worked: int, absence: int, actual: int}>, target: int, actual: int, balance: int}
	 */
	public static function calculate(string $from, string $to, WorkModel $model, string $state, array $reducingAbsences, array $workTimeAbsences, array $workedMinutes): array {
		$days = [];
		$target = 0;
		$actual = 0;
		foreach (AbsenceDays::dates($from, $to) as $date) {
			$key = $date->format('Y-m-d');
			$holiday = AbsenceDays::holidayName($key, $state);
			$modelMinutes = $model->minutesOn($date);
			$dayTarget = 0;
			$absenceMinutes = 0;
			if ($holiday === null) {
				$dayTarget = (int)round($modelMinutes * (1 - min(1.0, $reducingAbsences[$key] ?? 0.0)));
				$absenceMinutes = (int)round($modelMinutes * min(1.0, $workTimeAbsences[$key] ?? 0.0));
			}
			$worked = $workedMinutes[$key] ?? 0;
			$days[] = [
				'date' => $key,
				'holiday' => $holiday,
				'model' => $modelMinutes,
				'target' => $dayTarget,
				'worked' => $worked,
				'absence' => $absenceMinutes,
				'actual' => $worked + $absenceMinutes,
			];
			$target += $dayTarget;
			$actual += $worked + $absenceMinutes;
		}
		return ['days' => $days, 'target' => $target, 'actual' => $actual, 'balance' => $actual - $target];
	}
}
