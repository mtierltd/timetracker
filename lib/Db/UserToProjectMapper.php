<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<UserToProject>
 */
class UserToProjectMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_user_to_project', UserToProject::class);
    }

    /**
     * The link, or null.
     */
    public function find($id): ?UserToProject {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * @return UserToProject[]
     */
    public function findAllForUser($uid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($uid)));
        return $this->findEntities($qb);
    }

    /**
     * @return UserToProject[]
     */
    public function findAllForProject($pid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$pid, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    /**
     * The link between the user and the project, or null.
     *
     * @param Project $project
     */
    public function findForUserAndProject($uid, $project): ?UserToProject {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$project->id, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function deleteAllForProject($project_id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$project_id, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }
}
