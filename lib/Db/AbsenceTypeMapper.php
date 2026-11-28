<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<AbsenceType>
 */
class AbsenceTypeMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'timetracker_absence_type', AbsenceType::class);
	}

	public function findOrNull(int $id): ?AbsenceType {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * All types by id, archived ones included (old absences still use them).
	 *
	 * @return array<int, AbsenceType>
	 */
	public function findAllById(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('id');
		$types = [];
		foreach ($this->findEntities($qb) as $type) {
			$types[(int)$type->id] = $type;
		}
		return $types;
	}
}
