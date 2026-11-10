<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<WorkInterval>
 */
class WorkIntervalMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_work_interval', WorkInterval::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id): WorkInterval {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * The user's newest entries.
     *
     * @return WorkInterval[]
     */
    public function findLatest($user, $limit = 10, $offset = 0): array {
        return $this->findEntities($this->newestFirst($user, $limit, $offset));
    }

    /**
     * The user's newest entry with this name, or null.
     */
    public function findLatestByName($user, $name): ?WorkInterval {
        $qb = $this->newestFirst($user, 1, 0);
        $qb->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * The user's entries starting after $from and before $to (each optional),
     * newest first.
     *
     * @return WorkInterval[]
     */
    public function findLatestInterval($user, $from, $to, $limit = 5000, $offset = 0): array {
        $qb = $this->newestFirst($user, $limit, $offset);
        if (!empty($from)) {
            $qb->andWhere($qb->expr()->gt('start', $qb->createNamedParameter((int)$from, IQueryBuilder::PARAM_INT)));
        }
        if (!empty($to)) {
            $qb->andWhere($qb->expr()->lt('start', $qb->createNamedParameter((int)$to, IQueryBuilder::PARAM_INT)));
        }
        return $this->findEntities($qb);
    }

    /**
     * The user's running timers, newest first.
     *
     * @return WorkInterval[]
     */
    public function findAllRunning($user, $limit = 100, $offset = 0): array {
        $qb = $this->newestFirst($user, $limit, $offset);
        $qb->andWhere($qb->expr()->eq('running', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    /**
     * @return WorkInterval[]
     */
    public function findAllForProject($project_id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$project_id, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    public function deleteAllForProject($project_id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$project_id, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function newestFirst($user, $limit, $offset): IQueryBuilder {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($user)))
            ->orderBy('start', 'DESC');
        if ($limit !== null) {
            $qb->setMaxResults((int)$limit);
        }
        if (!empty($offset)) {
            $qb->setFirstResult((int)$offset);
        }
        return $qb;
    }
}
