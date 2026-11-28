<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Absence>
 */
class AbsenceMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'timetracker_absence', Absence::class);
	}

	public function findOrNull(int $id): ?Absence {
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
	 * Absences that overlap the period from $from to $to (Y-m-d, both
	 * included), oldest first.
	 *
	 * @param string|null $userUid null: all users
	 * @param string[]|null $statuses null: all statuses
	 * @return Absence[]
	 */
	public function findOverlapping(?string $userUid, string $from, string $to, ?array $statuses = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->lte('start_date', $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte('end_date', $qb->createNamedParameter($from)))
			->orderBy('start_date')
			->addOrderBy('id');
		if ($userUid !== null) {
			$qb->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)));
		}
		if ($statuses !== null) {
			$qb->andWhere($qb->expr()->in('status', $qb->createNamedParameter($statuses, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		return $this->findEntities($qb);
	}

	/**
	 * @return Absence[] all absences with this status, oldest first
	 */
	public function findByStatus(string $status): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter($status)))
			->orderBy('start_date')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}
}
