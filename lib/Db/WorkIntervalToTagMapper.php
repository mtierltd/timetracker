<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

class WorkIntervalToTagMapper extends CompatibleMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_workint_to_tag');
    }


    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_workint_to_tag` ' .
            'WHERE `id` = ?';
            
            try {
                $e = $this->findEntity($sql, [$id]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }

    public function findAllForWorkInterval($workIntervalId) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_workint_to_tag` ' .
            'WHERE `work_interval_id` = ?';
            
            try {
                $e = $this->findEntities($sql, [$workIntervalId]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }

    public function deleteAllForWorkInterval($workIntervalId) {
        $sql = 'DELETE FROM `*PREFIX*timetracker_workint_to_tag` ' .
            'WHERE `work_interval_id` = ?';
            
            try {
                $e = $this->execute($sql, [$workIntervalId]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }

    public function deleteAllForTag($tagId) {
        $sql = 'DELETE FROM `*PREFIX*timetracker_workint_to_tag` ' .
            'WHERE `tag_id` = ?';
            
            try {
                $e = $this->execute($sql, [$tagId]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }

   

    
   
}
