<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<GoalSkip>
 */
class GoalSkipMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'timetracker_goal_skip', GoalSkip::class);
	}

	/**
	 * The skipped days of several goals.
	 *
	 * @param int[] $goalIds
	 * @return array<int, GoalSkip[]> goal id => skips, by start date
	 */
	public function findForGoals(array $goalIds): array {
		if ($goalIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('goal_id', $qb->createNamedParameter($goalIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('start_date')
			->addOrderBy('id');
		$skips = [];
		foreach ($this->findEntities($qb) as $skip) {
			$skips[(int)$skip->goalId][] = $skip;
		}
		return $skips;
	}

	public function add(int $goalId, string $startDate, string $endDate): GoalSkip {
		$skip = new GoalSkip();
		$skip->setGoalId($goalId);
		$skip->setStartDate($startDate);
		$skip->setEndDate($endDate);
		return $this->insert($skip);
	}

	/** Removes one skip of a goal; false if the goal has no such skip. */
	public function remove(int $goalId, int $skipId): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($skipId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('goal_id', $qb->createNamedParameter($goalId, IQueryBuilder::PARAM_INT)))
			->executeStatement() > 0;
	}

	public function deleteForGoal(int $goalId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('goal_id', $qb->createNamedParameter($goalId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
