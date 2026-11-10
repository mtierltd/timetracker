<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<WorkIntervalToTag>
 */
class WorkIntervalToTagMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_workint_to_tag', WorkIntervalToTag::class);
    }

    /**
     * The link, or null.
     */
    public function find($id): ?WorkIntervalToTag {
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
     * @return WorkIntervalToTag[]
     */
    public function findAllForWorkInterval($workIntervalId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('work_interval_id', $qb->createNamedParameter((int)$workIntervalId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    /**
     * @return int number of deleted rows
     */
    public function deleteAllForWorkInterval($workIntervalId): int {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('work_interval_id', $qb->createNamedParameter((int)$workIntervalId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    /**
     * @return int number of deleted rows
     */
    public function deleteAllForTag($tagId): int {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('tag_id', $qb->createNamedParameter((int)$tagId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }
}
