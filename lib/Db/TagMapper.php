<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

class TagMapper extends CompatibleMapper {

    public function __construct(IDBConnection $db) {
        $this->dbengine = 'MYSQL';
        if (strpos(get_class($db->getDatabasePlatform()),'PostgreSQL') !== FALSE){
            $this->dbengine = 'POSTGRES';
        } else if (strpos(get_class($db->getDatabasePlatform()),'Sqlite') !== FALSE){
            $this->dbengine = 'SQLITE';
        }
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
        try {
            $e = $this->findEntity($sql, [$id]);
            return $e;
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
            return null;
        }
    }


    public function findAll($user){
        $sql = 'SELECT tt.* FROM `*PREFIX*timetracker_tag` tt  where tt.user_uid = ? order by tt.name asc';
        return $this->findEntities($sql, [$user]);
    }

    public function findAllAlowedForProject($pid){
        $sql = 'SELECT tt.* FROM `*PREFIX*timetracker_tag` tt  join `*PREFIX*timetracker_lpa_tags` atg on tt.id = atg.tag_id where atg.project_id = ? order by tt.name asc';
        
        return $this->findEntities($sql, [$pid]);
    }

    public function allowedTags($id, $tag_ids){
        $sql = 'delete from `*PREFIX*timetracker_lpa_tags` where project_id = ?';
        $this->execute($sql, [$id]);
        
        foreach ($tag_ids as $t){
            if (empty($t))
                continue;
            if ($this->dbengine == 'MYSQL'){
                $sql = "insert into `*PREFIX*timetracker_lpa_tags` (project_id, tag_id, created_at) values(?,?,UNIX_TIMESTAMP(now()))";
            } else if ($this->dbengine == 'POSTGRES'){
                $sql = "insert into `*PREFIX*timetracker_lpa_tags` (project_id, tag_id, created_at) values(?,?,extract(epoch from now()))";
            } else if ($this->dbengine == 'SQLITE'){
                $sql = "insert into `*PREFIX*timetracker_lpa_tags` (project_id, tag_id, created_at) values(?,?,strftime('%s', 'now'))";
            }
            $this->execute($sql, [$id, $t]);
        }
        return;
    }

   
}
