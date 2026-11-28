<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Db;

use OCA\TimeTracker\Service\WorkModel;
use OCP\AppFramework\Db\Entity;

/**
 * A user's stored work model (#376).
 *
 * @method string getUserUid()
 * @method void setUserUid(string $userUid)
 * @method string|null getAccountStart()
 * @method void setAccountStart(?string $accountStart)
 */
class WorkModelEntity extends Entity {

	public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

	public $id;
	public $userUid;
	public $minutesMon;
	public $minutesTue;
	public $minutesWed;
	public $minutesThu;
	public $minutesFri;
	public $minutesSat;
	public $minutesSun;
	public $accountStart;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userUid', 'string');
		foreach (self::DAYS as $day) {
			$this->addType('minutes' . $day, 'integer');
		}
		$this->addType('accountStart', 'string');
	}

	public function toModel(): WorkModel {
		$minutes = [];
		foreach (self::DAYS as $number => $day) {
			$minutes[$number] = (int)$this->{'minutes' . $day};
		}
		return new WorkModel($minutes);
	}

	public function setFromModel(WorkModel $model): void {
		foreach ($model->minutesPerWeekday() as $number => $minutes) {
			$this->setter('minutes' . self::DAYS[$number], [$minutes]);
		}
	}
}
