<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class TimelineEntryMapper extends Mapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_timeline_entry');
    }


    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_timeline_entry` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }

    /**
     */
    public function findTimelineEntries($tid) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_timeline_entry` ' .
            'WHERE `timeline_id` = ?';
        return $this->findEntities($sql, [$tid]);
    }

}