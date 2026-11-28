<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Days on which a goal is skipped, e.g. vacation (#379). The goal's target
 * is reduced by the share of its counted days that are skipped.
 *
 * @method int getGoalId()
 * @method void setGoalId(int $goalId)
 * @method string getStartDate()
 * @method void setStartDate(string $startDate)
 * @method string getEndDate()
 * @method void setEndDate(string $endDate)
 */
class GoalSkip extends Entity implements \JsonSerializable {

	public $id;
	public $goalId;
	public $startDate;
	public $endDate;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('goalId', 'integer');
		$this->addType('startDate', 'string');
		$this->addType('endDate', 'string');
	}

	public function jsonSerialize(): array {
		return ['id' => (int)$this->id, 'startDate' => $this->startDate, 'endDate' => $this->endDate];
	}
}
