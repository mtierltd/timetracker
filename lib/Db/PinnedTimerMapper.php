<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<PinnedTimer>
 */
class PinnedTimerMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_pin', PinnedTimer::class);
    }

    /**
     * The user's pins in their order.
     *
     * @return PinnedTimer[]
     */
    public function findAllForUser(string $user): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($user)))
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * The user's pin with this id, or null (also if it belongs to someone else).
     */
    public function findForUser($id, string $user): ?PinnedTimer {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($user)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * The user's pin with this name and project (null: no project), or null.
     */
    public function findByNameAndProject(string $user, string $name, ?int $projectId): ?PinnedTimer {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($user)))
            ->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)))
            ->setMaxResults(1);
        if ($projectId === null) {
            $qb->andWhere($qb->expr()->isNull('project_id'));
        } else {
            $qb->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        }
        $pins = $this->findEntities($qb);
        return $pins[0] ?? null;
    }

    /**
     * The highest sort order of the user's pins, or -1 without pins.
     */
    public function maxSortOrder(string $user): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->max('sort_order'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($user)));
        $result = $qb->executeQuery();
        $max = $result->fetchOne();
        $result->closeCursor();
        return ($max === null || $max === false) ? -1 : (int)$max;
    }

    /**
     * Removes the project from all pins that use it (the pins stay).
     */
    public function clearProject($projectId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('project_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$projectId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    /**
     * Removes the tag from all pins that use it.
     */
    public function removeTag($tagId): void {
        $tagId = (int)$tagId;
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->like('tag_ids', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter((string)$tagId) . '%')));
        foreach ($this->findEntities($qb) as $pin) {
            $ids = $pin->getTagIdList();
            if (in_array($tagId, $ids, true)) {
                $pin->setTagIds(implode(',', array_diff($ids, [$tagId])));
                $this->update($pin);
            }
        }
    }
}
