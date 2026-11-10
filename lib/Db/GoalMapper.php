<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Goal>
 */
class GoalMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_goal', Goal::class);
    }

    /**
     * The user's goal for this project, or null.
     */
    public function findByUserProject($userUid, $projectId): ?Goal {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)))
            ->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$projectId, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id): Goal {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * The user's goals with their project name, newest first.
     *
     * @return Goal[]
     */
    public function findAll($user): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('tg.*')
            ->selectAlias('p.name', 'project_name')
            ->from($this->getTableName(), 'tg')
            ->innerJoin('tg', 'timetracker_project', 'p', $qb->expr()->eq('p.id', 'tg.project_id'))
            ->where($qb->expr()->eq('tg.user_uid', $qb->createNamedParameter($user)))
            ->orderBy('tg.created_at', 'DESC');
        return $this->findEntities($qb);
    }
}
