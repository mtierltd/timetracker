<?php
namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

class PinnedTimerMapper extends CompatibleMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_pinned_timer');
    }

    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_pinned_timer` WHERE `id` = ?';
        try {
            return $this->findEntity($sql, [$id]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * @return PinnedTimer[]
     */
    public function findAll($user) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_pinned_timer` WHERE `user_uid` = ? ORDER BY `sort_order` ASC, `id` ASC';
        return $this->findEntities($sql, [$user]);
    }

    public function maxSortOrder($user): int {
        $max = -1;
        foreach ($this->findAll($user) as $pin) {
            $max = max($max, (int)$pin->sortOrder);
        }
        return $max;
    }

    /**
     * Clear the project of pins that use it, for one user or for everybody.
     */
    public function clearProject($projectId, $user = null): void {
        $sql = 'UPDATE `*PREFIX*timetracker_pinned_timer` SET `project_id` = NULL WHERE `project_id` = ?';
        $params = [(int)$projectId];
        if ($user !== null) {
            $sql .= ' AND `user_uid` = ?';
            $params[] = $user;
        }
        $this->execute($sql, $params)->closeCursor();
    }

    /**
     * Remove a deleted tag from all pins.
     */
    public function removeTag($tagId): void {
        $tagId = (int)$tagId;
        $sql = 'SELECT * FROM `*PREFIX*timetracker_pinned_timer` WHERE `tag_ids` LIKE ?';
        foreach ($this->findEntities($sql, ['%' . $tagId . '%']) as $pin) {
            $ids = $pin->getTagIdList();
            if (!in_array($tagId, $ids, true)) {
                continue; // e.g. tag 3 matched "13"
            }
            $pin->setTagIds(implode(',', array_values(array_diff($ids, [$tagId]))));
            $this->update($pin);
        }
    }
}
