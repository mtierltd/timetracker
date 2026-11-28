<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A user's vacation days in a year (#375), stored in half days.
 *
 * @method string getUserUid()
 * @method void setUserUid(string $userUid)
 * @method int getYear()
 * @method void setYear(int $year)
 * @method int getHalfDays()
 * @method void setHalfDays(int $halfDays)
 * @method int getCarriedOverHalfDays()
 * @method void setCarriedOverHalfDays(int $carriedOverHalfDays)
 * @method string|null getCarryOverExpiry()
 * @method void setCarryOverExpiry(?string $carryOverExpiry)
 */
class VacationEntitlement extends Entity implements \JsonSerializable {

	public $id;
	public $userUid;
	public $year;
	public $halfDays;
	public $carriedOverHalfDays;
	public $carryOverExpiry;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userUid', 'string');
		$this->addType('year', 'integer');
		$this->addType('halfDays', 'integer');
		$this->addType('carriedOverHalfDays', 'integer');
		$this->addType('carryOverExpiry', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'id' => (int)$this->id,
			'userUid' => $this->userUid,
			'year' => (int)$this->year,
			'days' => (int)$this->halfDays / 2,
			'carriedOver' => (int)$this->carriedOverHalfDays / 2,
			'carryOverExpiry' => $this->carryOverExpiry,
		];
	}
}
