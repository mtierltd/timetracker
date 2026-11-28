<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

use OCA\TimeTracker\Db\Absence;
use OCA\TimeTracker\Db\AbsenceMapper;
use OCA\TimeTracker\Db\AbsenceType;
use OCA\TimeTracker\Db\AbsenceTypeMapper;

/**
 * Creating, listing and cancelling absences (#374).
 */
class AbsenceService {

	public function __construct(
		private AbsenceMapper $absenceMapper,
		private AbsenceTypeMapper $typeMapper,
		private AbsenceSettings $settings,
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
		if ($this->absenceMapper->findOverlapping($userId, $startDate, $endDate, [Absence::STATUS_REQUESTED, Absence::STATUS_APPROVED]) !== []) {
			throw new AbsenceException('This period overlaps another absence');
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
		return $this->absenceMapper->insert($absence);
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
		$absence->setStatus(Absence::STATUS_CANCELLED);
		return $this->absenceMapper->update($absence);
	}
}
