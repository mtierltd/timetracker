<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

use OCA\TimeTracker\Db\Absence;
use OCA\TimeTracker\Db\AbsenceMapper;
use OCA\TimeTracker\Db\AbsenceType;
use OCA\TimeTracker\Db\AbsenceTypeMapper;
use OCA\TimeTracker\Db\VacationEntitlement;
use OCA\TimeTracker\Db\VacationEntitlementMapper;
use OCA\TimeTracker\Notification\AbsenceNotifications;

/**
 * Creating, listing, cancelling and approving absences (#374), and the
 * vacation balance (#375).
 */
class AbsenceService {

	public function __construct(
		private AbsenceMapper $absenceMapper,
		private AbsenceTypeMapper $typeMapper,
		private AbsenceSettings $settings,
		private VacationEntitlementMapper $entitlementMapper,
		private AbsenceNotifications $notifications,
	) {
	}

	/** The work model of a user. */
	public function workModel(string $userId): WorkModel {
		return new WorkModel();
	}

	/**
	 * The working days an absence covers, as Y-m-d => share of the day.
	 *
	 * @return array<string, float>
	 */
	public function workingDays(Absence $absence): array {
		return AbsenceDays::workingDays($absence->startDate, $absence->endDate,
			(bool)$absence->halfDayStart, (bool)$absence->halfDayEnd,
			$this->workModel($absence->userUid), $this->settings->stateFor($absence->userUid));
	}

	/**
	 * A user's absences that overlap a year, for the API.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function listForUser(string $userId, int $year): array {
		$types = $this->typeMapper->findAllById();
		$absences = $this->absenceMapper->findOverlapping($userId, sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year));
		return array_map(fn (Absence $a) => $this->toArray($a, $types[(int)$a->typeId] ?? null), $absences);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present(Absence $absence): array {
		return $this->toArray($absence, $this->typeMapper->findOrNull((int)$absence->typeId));
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Absence $absence, ?AbsenceType $type): array {
		return [
			'id' => (int)$absence->id,
			'userUid' => $absence->userUid,
			'typeId' => (int)$absence->typeId,
			'typeName' => $type?->name,
			'typeColor' => $type?->color,
			'startDate' => $absence->startDate,
			'endDate' => $absence->endDate,
			'halfDayStart' => (bool)$absence->halfDayStart,
			'halfDayEnd' => (bool)$absence->halfDayEnd,
			'status' => $absence->status,
			'note' => $absence->note,
			'decidedBy' => $absence->decidedBy,
			'decidedAt' => $absence->decidedAt,
			'decisionComment' => $absence->decisionComment,
			'createdAt' => (int)$absence->createdAt,
			'days' => array_sum($this->workingDays($absence)),
		];
	}

	/**
	 * Creates an absence for a user. Types that need approval start as
	 * requested, others (e.g. illness, which is only reported) as approved.
	 *
	 * @throws AbsenceException if the input isn't valid
	 */
	public function create(string $userId, mixed $typeId, mixed $startDate, mixed $endDate, bool $halfDayStart, bool $halfDayEnd, ?string $note): Absence {
		$type = is_numeric($typeId) ? $this->typeMapper->findOrNull((int)$typeId) : null;
		if ($type === null || $type->archived) {
			throw new AbsenceException('Please choose an absence type');
		}
		if (!AbsenceDays::isDate($startDate) || !AbsenceDays::isDate($endDate)) {
			throw new AbsenceException('Please enter a valid start and end date');
		}
		if ($endDate < $startDate) {
			throw new AbsenceException('The end date must not be before the start date');
		}
		if ($startDate === $endDate && $halfDayStart && $halfDayEnd) {
			// a single half day: one flag is enough
			$halfDayEnd = false;
		}
		$types = $this->typeMapper->findAllById();
		foreach ($this->absenceMapper->findOverlapping($userId, $startDate, $endDate, [Absence::STATUS_REQUESTED, Absence::STATUS_APPROVED]) as $other) {
			// Only an absence that doesn't deduct vacation (e.g. illness) may
			// overlap vacation: the vacation days it covers are given back.
			$otherType = $types[(int)$other->typeId] ?? null;
			if ($type->deductsVacation || $otherType === null || !$otherType->deductsVacation) {
				throw new AbsenceException('This period overlaps another absence');
			}
		}
		$note = $note === null ? null : mb_substr(trim($note), 0, 255);

		$absence = new Absence();
		$absence->setUserUid($userId);
		$absence->setTypeId((int)$type->id);
		$absence->setStartDate($startDate);
		$absence->setEndDate($endDate);
		$absence->setHalfDayStart($halfDayStart ? 1 : 0);
		$absence->setHalfDayEnd($halfDayEnd ? 1 : 0);
		$absence->setNote($note === '' ? null : $note);
		$absence->setCreatedAt(time());
		$absence->setStatus($type->needsApproval ? Absence::STATUS_REQUESTED : Absence::STATUS_APPROVED);
		if ($this->workingDays($absence) === []) {
			throw new AbsenceException('This period contains no working days');
		}
		$absence = $this->absenceMapper->insert($absence);
		if ($absence->status === Absence::STATUS_REQUESTED) {
			$this->notifications->requested($absence);
		}
		return $absence;
	}

	/**
	 * Cancels one of the user's own requested or approved absences.
	 *
	 * @throws AbsenceException
	 */
	public function cancel(string $userId, int $id): Absence {
		$absence = $this->absenceMapper->findOrNull($id);
		if ($absence === null || $absence->userUid !== $userId) {
			throw new AbsenceException('Not found', 404);
		}
		if (!$absence->isActive()) {
			throw new AbsenceException('Only requested or approved absences can be cancelled');
		}
		$wasRequested = $absence->status === Absence::STATUS_REQUESTED;
		$absence->setStatus(Absence::STATUS_CANCELLED);
		$absence = $this->absenceMapper->update($absence);
		if ($wasRequested) {
			$this->notifications->removeRequest($absence);
		}
		return $absence;
	}

	/**
	 * An approver approves or rejects a requested absence.
	 *
	 * @throws AbsenceException
	 */
	public function decide(string $approverId, int $id, bool $approve, ?string $comment): Absence {
		$absence = $this->absenceMapper->findOrNull($id);
		if ($absence === null) {
			throw new AbsenceException('Not found', 404);
		}
		if ($absence->status !== Absence::STATUS_REQUESTED) {
			throw new AbsenceException('Only requested absences can be approved or rejected');
		}
		$comment = $comment === null ? '' : mb_substr(trim($comment), 0, 255);
		$absence->setStatus($approve ? Absence::STATUS_APPROVED : Absence::STATUS_REJECTED);
		$absence->setDecidedBy($approverId);
		$absence->setDecidedAt(time());
		$absence->setDecisionComment($comment === '' ? null : $comment);
		$absence = $this->absenceMapper->update($absence);
		$this->notifications->decided($absence);
		return $absence;
	}

	/**
	 * All requested absences, for approvers.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function listRequested(): array {
		$types = $this->typeMapper->findAllById();
		return array_map(fn (Absence $a) => $this->toArray($a, $types[(int)$a->typeId] ?? null),
			$this->absenceMapper->findByStatus(Absence::STATUS_REQUESTED));
	}

	/**
	 * A user's vacation days in a year: entitlement, taken, requested, left.
	 *
	 * @return array<string, mixed>
	 */
	public function vacationBalance(string $userId, int $year): array {
		$first = sprintf('%04d-01-01', $year);
		$last = sprintf('%04d-12-31', $year);
		$types = $this->typeMapper->findAllById();
		$approved = [];
		$requested = [];
		$other = [];
		foreach ($this->absenceMapper->findOverlapping($userId, $first, $last, [Absence::STATUS_REQUESTED, Absence::STATUS_APPROVED]) as $absence) {
			$type = $types[(int)$absence->typeId] ?? null;
			if ($type === null) {
				continue;
			}
			$days = array_filter($this->workingDays($absence), fn ($date) => $date >= $first && $date <= $last, ARRAY_FILTER_USE_KEY);
			if ($type->deductsVacation) {
				if ($absence->status === Absence::STATUS_APPROVED) {
					$approved += $days;
				} else {
					$requested += $days;
				}
			} elseif ($absence->status === Absence::STATUS_APPROVED) {
				foreach ($days as $date => $share) {
					$other[$date] = min(1.0, ($other[$date] ?? 0.0) + $share);
				}
			}
		}
		$entitlement = $this->entitlementMapper->findForUserYear($userId, $year);
		return VacationBalance::calculate(
			$entitlement === null ? 0.0 : $entitlement->halfDays / 2,
			$entitlement === null ? 0.0 : $entitlement->carriedOverHalfDays / 2,
			$entitlement?->carryOverExpiry,
			VacationBalance::withoutOtherAbsences($approved, $other),
			VacationBalance::withoutOtherAbsences($requested, $other),
			date('Y-m-d'),
		) + ['hasEntitlement' => $entitlement !== null];
	}

	/**
	 * Sets a user's vacation entitlement for a year.
	 *
	 * @throws AbsenceException
	 */
	public function saveEntitlement(string $userId, int $year, mixed $days, mixed $carriedOver, mixed $carryOverExpiry): VacationEntitlement {
		$halfDays = self::toHalfDays($days);
		$carriedHalfDays = self::toHalfDays($carriedOver ?? 0);
		if ($halfDays === null || $carriedHalfDays === null) {
			throw new AbsenceException('Days must be a number of whole or half days from 0 to 366');
		}
		if ($carryOverExpiry === '' || $carryOverExpiry === null) {
			$carryOverExpiry = null;
		} elseif (!AbsenceDays::isDate($carryOverExpiry)) {
			throw new AbsenceException('Please enter a valid expiry date');
		}
		$entitlement = $this->entitlementMapper->findForUserYear($userId, $year) ?? new VacationEntitlement();
		$entitlement->setUserUid($userId);
		$entitlement->setYear($year);
		$entitlement->setHalfDays($halfDays);
		$entitlement->setCarriedOverHalfDays($carriedHalfDays);
		$entitlement->setCarryOverExpiry($carryOverExpiry);
		return $entitlement->id ? $this->entitlementMapper->update($entitlement) : $this->entitlementMapper->insert($entitlement);
	}

	/** "12.5" => 25; null if not a multiple of 0.5 from 0 to 366. */
	public static function toHalfDays(mixed $days): ?int {
		if (!is_numeric($days)) {
			return null;
		}
		$half = (float)$days * 2;
		if ($half < 0 || $half > 732 || abs($half - round($half)) > 1e-9) {
			return null;
		}
		return (int)round($half);
	}
}
