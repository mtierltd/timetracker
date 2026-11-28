<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A kind of absence, defined by admins (#374).
 *
 * @method string getName()
 * @method void setName(string $name)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method string getCountsAs()
 * @method void setCountsAs(string $countsAs)
 * @method int getDeductsVacation()
 * @method void setDeductsVacation(int $deductsVacation)
 * @method int getNeedsApproval()
 * @method void setNeedsApproval(int $needsApproval)
 * @method string getVisibility()
 * @method void setVisibility(string $visibility)
 * @method int getArchived()
 * @method void setArchived(int $archived)
 */
class AbsenceType extends Entity implements \JsonSerializable {

	public const COUNTS_AS = ['target_reduction', 'work_time'];
	public const VISIBILITIES = ['reason', 'busy_only'];

	public $id;
	public $name;
	public $color;
	public $countsAs;
	public $deductsVacation;
	public $needsApproval;
	public $visibility;
	public $archived;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('name', 'string');
		$this->addType('color', 'string');
		$this->addType('countsAs', 'string');
		$this->addType('deductsVacation', 'integer');
		$this->addType('needsApproval', 'integer');
		$this->addType('visibility', 'string');
		$this->addType('archived', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => (int)$this->id,
			'name' => $this->name,
			'color' => $this->color,
			'countsAs' => $this->countsAs,
			'deductsVacation' => (bool)$this->deductsVacation,
			'needsApproval' => (bool)$this->needsApproval,
			'visibility' => $this->visibility,
			'archived' => (bool)$this->archived,
		];
	}
}
