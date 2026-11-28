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
	 * The skipped periods of several goals.
	 *
	 * @param int[] $goalIds
	 * @return array<int, string[]> goal id => periods, sorted
	 */
	public function findPeriodsForGoals(array $goalIds): array {
		if ($goalIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('goal_id', $qb->createNamedParameter($goalIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('period');
		$periods = [];
		foreach ($this->findEntities($qb) as $skip) {
			$periods[(int)$skip->goalId][] = $skip->period;
		}
		return $periods;
	}

	public function skip(int $goalId, string $period): void {
		if (in_array($period, $this->findPeriodsForGoals([$goalId])[$goalId] ?? [], true)) {
			return;
		}
		$skip = new GoalSkip();
		$skip->setGoalId($goalId);
		$skip->setPeriod($period);
		$this->insert($skip);
	}

	public function unskip(int $goalId, string $period): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('goal_id', $qb->createNamedParameter($goalId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('period', $qb->createNamedParameter($period)))
			->executeStatement();
	}

	public function deleteForGoal(int $goalId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('goal_id', $qb->createNamedParameter($goalId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
