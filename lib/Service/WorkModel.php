<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

/**
 * Target working minutes per weekday of a user (#376). Without a stored
 * model, a user works 8 hours Monday to Friday.
 */
final class WorkModel {

	public const DEFAULT_MINUTES = [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0];

	/** @var array<int, int> ISO weekday (1 = Monday) => minutes */
	private array $minutes;

	/**
	 * @param array<int, int> $minutes ISO weekday (1 = Monday ... 7 = Sunday) => minutes; missing days are 0
	 */
	public function __construct(array $minutes = self::DEFAULT_MINUTES) {
		$this->minutes = [];
		for ($day = 1; $day <= 7; $day++) {
			$this->minutes[$day] = max(0, (int)($minutes[$day] ?? 0));
		}
	}

	/** @return array<int, int> */
	public function minutesPerWeekday(): array {
		return $this->minutes;
	}

	public function minutesOn(\DateTimeInterface $date): int {
		return $this->minutes[(int)$date->format('N')];
	}

	public function isWorkingDay(\DateTimeInterface $date): bool {
		return $this->minutesOn($date) > 0;
	}

	public function minutesPerWeek(): int {
		return array_sum($this->minutes);
	}
}
