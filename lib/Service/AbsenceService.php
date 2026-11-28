<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

use OCA\TimeTracker\Db\Absence;
use OCA\TimeTracker\Db\AbsenceMapper;
use OCA\TimeTracker\Db\AbsenceType;
use OCA\TimeTracker\Db\AbsenceTypeMapper;
use OCA\TimeTracker\Db\VacationEntitlement;
use OCA\TimeTracker\Db\VacationEntitlementMapper;
use OCA\TimeTracker\Db\WorkModelEntity;
use OCA\TimeTracker\Db\WorkModelMapper;
use OCA\TimeTracker\Notification\AbsenceNotifications;

/**
 * Creating, listing, cancelling and approving absences (#374), and the
 * vacation balance (#375), work models and the time account (#376), the
 * team calendar and the out-of-office sync (#377).
 */
class AbsenceService {

	public function __construct(
		private AbsenceMapper $absenceMapper,
		private AbsenceTypeMapper $typeMapper,
		private AbsenceSettings $settings,
		private VacationEntitlementMapper $entitlementMapper,
		private AbsenceNotifications $notifications,
		private WorkModelMapper $workModelMapper,
		private WorkedTime $workedTime,
		private UserTimeZone $userTimeZone,
		private OutOfOfficeSync $outOfOffice,
	) {
	}

	/** @var array<string, WorkModel> */
	private array $workModels = [];

	/** The work model of a user: stored by an admin, or 8 h Monday to Friday. */
	public function workModel(string $userId): WorkModel {
		if (!isset($this->workModels[$userId])) {
			$this->workModels[$userId] = $this->workModelMapper->findForUser($userId)?->toModel() ?? new WorkModel();
		}
		return $this->workModels[$userId];
	}

	/**
	 * @return array{minutes: array<int, int>, accountStart: ?string, isDefault: bool}
	 */
	public function workModelInfo(string $userId): array {
		$stored = $this->workModelMapper->findForUser($userId);
		return [
			'minutes' => $this->workModel($userId)->minutesPerWeekday(),
			'accountStart' => $stored?->accountStart,
			'isDefault' => $stored === null,
		];
	}

	/**
	 * Admins set a user's minutes per weekday and the start of their account.
	 *
	 * @param mixed $minutes ISO weekday => minutes
	 * @throws AbsenceException
	 */
	public function saveWorkModel(string $userId, mixed $minutes, mixed $accountStart): void {
		if (!is_array($minutes)) {
			throw new AbsenceException('Please enter the hours per weekday');
		}
		$clean = [];
		for ($day = 1; $day <= 7; $day++) {
			$value = $minutes[$day] ?? $minutes[(string)$day] ?? 0;
			if (!is_numeric($value) || (int)$value < 0 || (int)$value > 24 * 60) {
				throw new AbsenceException('Hours per day must be between 0 and 24');
			}
			$clean[$day] = (int)$value;
		}
		if ($accountStart === '' || $accountStart === null) {
			$accountStart = null;
		} elseif (!AbsenceDays::isDate($accountStart)) {
			throw new AbsenceException('Please enter a valid start date');
		}
		$entity = $this->workModelMapper->findForUser($userId) ?? new WorkModelEntity();
		$entity->setUserUid($userId);
		$entity->setFromModel(new WorkModel($clean));
		$entity->setAccountStart($accountStart);
		$entity->id ? $this->workModelMapper->update($entity) : $this->workModelMapper->insert($entity);
		unset($this->workModels[$userId]);
	}

	/**
	 * Target, actual and balance per day of a month, and the balance of the
	 * year up to today. Days before the account start and after today don't
	 * count towards the balances.
	 *
	 * @param string $month Y-m
	 * @return array<string, mixed>
	 */
	public function timeAccount(string $userId, string $month, ?string $today = null): array {
		$today ??= (new \DateTimeImmutable('now', $this->userTimeZone->get($userId)))->format('Y-m-d');
		$first = $month . '-01';
		$last = (new \DateTimeImmutable($first))->format('Y-m-t');
		$yearStart = substr($month, 0, 4) . '-01-01';
		$accountStart = $this->workModelMapper->findForUser($userId)?->accountStart;
		$countFrom = max($yearStart, $accountStart ?? $yearStart);

		$monthAccount = $this->account($userId, $first, $last);
		$counted = array_values(array_filter($monthAccount['days'], fn ($d) => $d['date'] >= $countFrom && $d['date'] <= $today));
		$monthTarget = array_sum(array_column($counted, 'target'));
		$monthActual = array_sum(array_column($counted, 'actual'));

		$yearBalance = null;
		$yearEnd = min($today, substr($month, 0, 4) . '-12-31');
		if ($countFrom <= $yearEnd) {
			$yearBalance = $this->account($userId, $countFrom, $yearEnd)['balance'];
		}
		return [
			'month' => $month,
			'today' => $today,
			'countFrom' => $countFrom,
			'workModel' => $this->workModelInfo($userId),
			'days' => $monthAccount['days'],
			'target' => $monthTarget,
			'actual' => $monthActual,
			'balance' => $monthActual - $monthTarget,
			'yearBalance' => $yearBalance,
		];
	}

	/**
	 * @return array{days: list<array<string, mixed>>, target: int, actual: int, balance: int}
	 */
	private function account(string $userId, string $from, string $to): array {
		$types = $this->typeMapper->findAllById();
		$reducing = [];
		$workTime = [];
		foreach ($this->absenceMapper->findOverlapping($userId, $from, $to, [Absence::STATUS_APPROVED]) as $absence) {
			$type = $types[(int)$absence->typeId] ?? null;
			if ($type === null) {
				continue;
			}
			foreach ($this->workingDays($absence) as $date => $share) {
				if ($type->countsAs === 'work_time') {
					$workTime[$date] = min(1.0, ($workTime[$date] ?? 0.0) + $share);
				} else {
					$reducing[$date] = min(1.0, ($reducing[$date] ?? 0.0) + $share);
				}
			}
		}
		return TimeAccount::calculate($from, $to, $this->workModel($userId), $this->settings->stateFor($userId),
			$reducing, $workTime, $this->workedTime->minutesPerDay($userId, $from, $to));
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
			'conflictDays' => $this->conflictDays($absence, $type),
		];
	}

	/**
	 * Days of an active absence that reduces the target on which the user
	 * also recorded working time (#377).
	 *
	 * @return list<string> Y-m-d
	 */
	public function conflictDays(Absence $absence, ?AbsenceType $type): array {
		if (!$absence->isActive() || $type === null || $type->countsAs !== 'target_reduction') {
			return [];
		}
		$worked = $this->workedTime->minutesPerDay($absence->userUid, $absence->startDate, $absence->endDate);
		return array_values(array_filter(array_keys($this->workingDays($absence)), fn ($date) => ($worked[$date] ?? 0) > 0));
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
		} else {
			$this->outOfOffice->sync($userId);
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
		} else {
			$this->outOfOffice->sync($userId);
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
		if ($approve) {
			$this->outOfOffice->sync($absence->userUid);
		}
		return $absence;
	}

	/**
	 * Requested and approved absences of several users in a period, for the
	 * team calendar. Others only see the type if its visibility allows it,
	 * and never the note; $fullDetails (approvers) see everything.
	 *
	 * @param string[] $userIds
	 * @return list<array<string, mixed>>
	 */
	public function teamAbsences(array $userIds, string $from, string $to, string $viewerId, bool $fullDetails): array {
		$types = $this->typeMapper->findAllById();
		$wanted = array_flip($userIds);
		$result = [];
		foreach ($this->absenceMapper->findOverlapping(null, $from, $to, [Absence::STATUS_REQUESTED, Absence::STATUS_APPROVED]) as $absence) {
			if (!isset($wanted[$absence->userUid])) {
				continue;
			}
			$type = $types[(int)$absence->typeId] ?? null;
			$visible = $fullDetails || $absence->userUid === $viewerId || ($type !== null && $type->visibility === 'reason');
			$result[] = [
				'id' => (int)$absence->id,
				'userUid' => $absence->userUid,
				'typeName' => $visible ? $type?->name : 'Absent',
				'typeColor' => $visible ? $type?->color : '#9e9e9e',
				'startDate' => $absence->startDate,
				'endDate' => $absence->endDate,
				'halfDayStart' => (bool)$absence->halfDayStart,
				'halfDayEnd' => (bool)$absence->halfDayEnd,
				'status' => $absence->status,
				'note' => $fullDetails || $absence->userUid === $viewerId ? $absence->note : null,
				'workingDays' => $this->workingDays($absence),
			];
		}
		return $result;
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
			(new \DateTimeImmutable('now', $this->userTimeZone->get($userId)))->format('Y-m-d'),
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
