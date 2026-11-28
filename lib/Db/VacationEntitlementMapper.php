<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<VacationEntitlement>
 */
class VacationEntitlementMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'timetracker_vacation_entitlement', VacationEntitlement::class);
	}

	public function findForUserYear(string $userUid, int $year): ?VacationEntitlement {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)))
			->andWhere($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/** @return VacationEntitlement[] all users' entitlements of a year */
	public function findForYear(int $year): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)))
			->orderBy('user_uid');
		return $this->findEntities($qb);
	}
}
