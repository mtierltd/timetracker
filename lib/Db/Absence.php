<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * An absence of a user over whole days (#374). Absences are never deleted:
 * cancelling or rejecting one changes its status.
 *
 * @method string getUserUid()
 * @method void setUserUid(string $userUid)
 * @method int getTypeId()
 * @method void setTypeId(int $typeId)
 * @method string getStartDate()
 * @method void setStartDate(string $startDate)
 * @method string getEndDate()
 * @method void setEndDate(string $endDate)
 * @method int getHalfDayStart()
 * @method void setHalfDayStart(int $halfDayStart)
 * @method int getHalfDayEnd()
 * @method void setHalfDayEnd(int $halfDayEnd)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getNote()
 * @method void setNote(?string $note)
 * @method string|null getDecidedBy()
 * @method void setDecidedBy(?string $decidedBy)
 * @method int|null getDecidedAt()
 * @method void setDecidedAt(?int $decidedAt)
 * @method string|null getDecisionComment()
 * @method void setDecisionComment(?string $decisionComment)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Absence extends Entity {

	public const STATUS_REQUESTED = 'requested';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_CANCELLED = 'cancelled';

	public $id;
	public $userUid;
	public $typeId;
	public $startDate;
	public $endDate;
	public $halfDayStart;
	public $halfDayEnd;
	public $status;
	public $note;
	public $decidedBy;
	public $decidedAt;
	public $decisionComment;
	public $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userUid', 'string');
		$this->addType('typeId', 'integer');
		$this->addType('startDate', 'string');
		$this->addType('endDate', 'string');
		$this->addType('halfDayStart', 'integer');
		$this->addType('halfDayEnd', 'integer');
		$this->addType('status', 'string');
		$this->addType('note', 'string');
		$this->addType('decidedBy', 'string');
		$this->addType('decidedAt', 'integer');
		$this->addType('decisionComment', 'string');
		$this->addType('createdAt', 'integer');
	}

	/** Requested and approved absences count; rejected and cancelled ones don't. */
	public function isActive(): bool {
		return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
	}
}
