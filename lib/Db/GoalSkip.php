<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A week (its Monday, Y-m-d) or month (Y-m) in which a goal is skipped:
 * its target is 0 then (#379).
 *
 * @method int getGoalId()
 * @method void setGoalId(int $goalId)
 * @method string getPeriod()
 * @method void setPeriod(string $period)
 */
class GoalSkip extends Entity {

	public $id;
	public $goalId;
	public $period;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('goalId', 'integer');
		$this->addType('period', 'string');
	}
}
