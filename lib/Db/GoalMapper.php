<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

class GoalMapper extends CompatibleMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_goal');
    }


    public function findByUserProject($userUid, $projectId) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_goal` ' .
            'WHERE  `user_uid` = ? and `project_id` = ?';
            
            try {
                $e = $this->findEntity($sql, [$userUid, $projectId]);
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
        $sql = 'SELECT * FROM `*PREFIX*timetracker_goal` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }


    public function findAll($user){
        $sql = 'SELECT tg.*,p.name as project_name FROM `*PREFIX*timetracker_goal` tg  join `*PREFIX*timetracker_project` p on p.id = tg.project_id where tg.user_uid = ? order by tg.created_at desc';
        return $this->findEntities($sql, [$user]);
    }

   
}
