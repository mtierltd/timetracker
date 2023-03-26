<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

class WorkItemMapper extends CompatibleMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_work_item');
    }


    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */

     

    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_item` ' .
            'WHERE `id` = ?';
            try {
                $e = $this->findEntity($sql, [$id]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
    }

    public function findByName($name) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_item` ' .
            'WHERE `name` = ?';
            
            try {
                $e = $this->findEntity($sql, [$name]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }


    public function findAll($limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_item`';
        return $this->findEntities($sql, $limit, $offset);
    }

}
