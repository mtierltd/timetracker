<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class TagMapper extends Mapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_tag');
    }


    public function findByNameUser($name, $userUid) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_tag` ' .
            'WHERE upper(`name`) = ? and `user_uid` = ?';
            
            try {
                $e = $this->findEntity($sql, [strtoupper($name), $userUid]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_tag` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }


    public function findAll($user){
        $sql = 'SELECT tt.* FROM `*PREFIX*timetracker_tag` tt  where tt.user_uid = ? order by tt.name asc';
        return $this->findEntities($sql, [$user]);
    }

    public function findAllAlowedForProject($pid){
        $sql = 'SELECT tt.* FROM `*PREFIX*timetracker_tag` tt  join `*PREFIX*timetracker_locked_project_allowed_tag` atg on tt.id = atg.tag_id where atg.project_id = ? order by tt.name asc';
        
        return $this->findEntities($sql, [$pid]);
    }

    public function allowedTags($id, $tag_ids){
        $sql = 'delete from `*PREFIX*timetracker_locked_project_allowed_tag` where project_id = ?';
        $this->execute($sql, [$id]);
        
        foreach ($tag_ids as $t){
            if (empty($t))
                continue;
            $sql = 'insert into `*PREFIX*timetracker_locked_project_allowed_tag` (project_id, tag_id, created_at) values(?,?,UNIX_TIMESTAMP(now()))  ';
            $this->execute($sql, [$id, $t]);
        }
        return;
    }

   
}