<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Project>
 */
class ProjectMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_project', Project::class);
    }

    /**
     * The project with this name, ignoring case, or null.
     */
    public function findByName($name): ?Project {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq($qb->func()->lower('name'), $qb->func()->lower($qb->createNamedParameter($name))));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * The user's projects whose name contains $name, ignoring case, by name.
     *
     * @return Project[]
     */
    public function searchByName($user, string $name): array {
        $qb = $this->forUser($user);
        $qb->andWhere($qb->expr()->iLike('tp.name', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($name) . '%')));
        return $this->findEntities($qb);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id): Project {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * The user's projects by name, without archived ones unless $getArchived.
     *
     * @return Project[]
     */
    public function findAll($user, $getArchived = 0): array {
        $qb = $this->forUser($user);
        if (!$getArchived) {
            $this->withoutArchived($qb);
        }
        return $this->findEntities($qb);
    }

    /**
     * All projects by name, without archived ones unless $getArchived.
     *
     * @return Project[]
     */
    public function findAllAdmin($getArchived = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('tp.*')
            ->from($this->getTableName(), 'tp')
            ->orderBy('tp.name', 'ASC');
        if (!$getArchived) {
            $this->withoutArchived($qb);
        }
        return $this->findEntities($qb);
    }

    public function deleteById($projectId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$projectId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function forUser($user): IQueryBuilder {
        $qb = $this->db->getQueryBuilder();
        $qb->select('tp.*')
            ->from($this->getTableName(), 'tp')
            ->innerJoin('tp', 'timetracker_user_to_project', 'up', $qb->expr()->eq('up.project_id', 'tp.id'))
            ->where($qb->expr()->eq('up.user_uid', $qb->createNamedParameter($user)))
            ->orderBy('tp.name', 'ASC');
        return $qb;
    }

    private function withoutArchived(IQueryBuilder $qb): void {
        $qb->andWhere($qb->expr()->neq('tp.archived', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
    }
}
