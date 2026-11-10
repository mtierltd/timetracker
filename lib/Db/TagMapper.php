<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Tag>
 */
class TagMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_tag', Tag::class);
    }

    /**
     * The user's tag with this name, ignoring case, or null.
     */
    public function findByNameUser($name, $userUid): ?Tag {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq($qb->func()->lower('name'), $qb->func()->lower($qb->createNamedParameter($name))))
            ->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * The tag, or null.
     *
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id): ?Tag {
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
     * The user's tags, by name.
     *
     * @return Tag[]
     */
    public function findAll($user): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($user)))
            ->orderBy('name', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * The tags allowed for a project, by name.
     *
     * @return Tag[]
     */
    public function findAllAlowedForProject($pid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('tt.*')
            ->from($this->getTableName(), 'tt')
            ->innerJoin('tt', 'timetracker_lpa_tags', 'atg', $qb->expr()->eq('tt.id', 'atg.tag_id'))
            ->where($qb->expr()->eq('atg.project_id', $qb->createNamedParameter((int)$pid, IQueryBuilder::PARAM_INT)))
            ->orderBy('tt.name', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Replace the tags allowed for a project. Empty ids are skipped.
     */
    public function allowedTags($id, $tag_ids): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('timetracker_lpa_tags')
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
            ->executeStatement();

        foreach ($tag_ids as $t) {
            if (empty($t)) {
                continue;
            }
            $qb = $this->db->getQueryBuilder();
            $qb->insert('timetracker_lpa_tags')
                ->values([
                    'project_id' => $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT),
                    'tag_id' => $qb->createNamedParameter((int)$t, IQueryBuilder::PARAM_INT),
                    'created_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
                ])
                ->executeStatement();
        }
    }
}
