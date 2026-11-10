<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Client>
 */
class ClientMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_client', Client::class);
    }

    /**
     * The client with this name, ignoring case, or null.
     */
    public function findByName($name): ?Client {
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
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id): Client {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * The user's clients, by name.
     *
     * @return Client[]
     */
    public function findAll($user): array {
        return $this->findEntities($this->forUser($user));
    }

    /**
     * The user's clients whose name contains $name, ignoring case, by name.
     *
     * @return Client[]
     */
    public function searchByName($user, $name): array {
        $qb = $this->forUser($user);
        $qb->andWhere($qb->expr()->iLike('tc.name', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($name) . '%')));
        return $this->findEntities($qb);
    }

    private function forUser($user): IQueryBuilder {
        $qb = $this->db->getQueryBuilder();
        $qb->select('tc.*')
            ->from($this->getTableName(), 'tc')
            ->innerJoin('tc', 'timetracker_user_to_client', 'uc', $qb->expr()->eq('uc.client_id', 'tc.id'))
            ->where($qb->expr()->eq('uc.user_uid', $qb->createNamedParameter($user)))
            ->orderBy('tc.name', 'ASC');
        return $qb;
    }
}
