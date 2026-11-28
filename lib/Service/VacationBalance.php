<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

/**
 * Vacation days taken and left in a year (#375). Pure calculation, no
 * database access.
 */
final class VacationBalance {

	/**
	 * Vacation days per date, without the days covered by another approved
	 * absence that doesn't deduct vacation: illness during vacation gives
	 * the vacation day back (§ 9 BUrlG).
	 *
	 * @param array<string, float> $vacationDays Y-m-d => share of the day
	 * @param array<string, float> $otherDays Y-m-d => share of the day, of approved absences that don't deduct vacation
	 * @return array<string, float>
	 */
	public static function withoutOtherAbsences(array $vacationDays, array $otherDays): array {
		$result = [];
		foreach ($vacationDays as $date => $share) {
			$left = $share - ($otherDays[$date] ?? 0.0);
			if ($left > 0) {
				$result[$date] = $left;
			}
		}
		return $result;
	}

	/**
	 * @param float $entitlement vacation days of the year
	 * @param float $carriedOver days carried over from the year before
	 * @param string|null $carryOverExpiry Y-m-d after which carried-over days not taken expire; null: never
	 * @param array<string, float> $approved approved vacation days of the year, Y-m-d => share
	 * @param array<string, float> $requested requested vacation days of the year, Y-m-d => share
	 * @param string $today Y-m-d
	 * @return array{entitlement: float, carriedOver: float, carryOverExpiry: ?string, carriedOverExpired: float, approved: float, requested: float, remaining: float}
	 */
	public static function calculate(float $entitlement, float $carriedOver, ?string $carryOverExpiry, array $approved, array $requested, string $today): array {
		$carriedOverExpired = 0.0;
		if ($carryOverExpiry !== null && $today > $carryOverExpiry) {
			// carried-over days are used first; those not used by the expiry date are lost
			$takenByExpiry = 0.0;
			foreach ($approved + $requested as $date => $share) {
				if ($date <= $carryOverExpiry) {
					$takenByExpiry += $share;
				}
			}
			$carriedOverExpired = max(0.0, $carriedOver - $takenByExpiry);
		}
		$approvedDays = array_sum($approved);
		$requestedDays = array_sum($requested);
		return [
			'entitlement' => $entitlement,
			'carriedOver' => $carriedOver,
			'carryOverExpiry' => $carryOverExpiry,
			'carriedOverExpired' => $carriedOverExpired,
			'approved' => $approvedDays,
			'requested' => $requestedDays,
			'remaining' => $entitlement + $carriedOver - $carriedOverExpired - $approvedDays - $requestedDays,
		];
	}
}
