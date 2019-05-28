<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class WorkIntervalMapper extends Mapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_work_interval');
    }


    public function findByName($name) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_interval` ' .
            'WHERE `name` = ?';
            
            try {
                $e = $this->findEntity($sql, [$name]);
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
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_interval` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }


    public function findAllForWorkItem($workItemId, $limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_interval` where `work_item_id` = ?';
        return $this->findEntities($sql, [$workItemId],$limit, $offset);
    }

    public function findLatest($user, $limit = 10, $offset = 0){
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_interval` where user_uid = ? order by id desc';
        return $this->findEntities($sql, [$user],$limit, $offset);
    }

    public function findLatestByName($user, $name){
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_interval` where user_uid = ? and name = ? order by id desc';
        try {
            return $this->findEntity($sql, [$user, $name], 1, 0);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
            return null;
        }
    }

    public function findLatestDays($user, $limitDays = 10, $startDay = 0, $limit = 100, $offset = 0){
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_interval` where user_uid = ? and 
                start > unix_timestamp(curdate() + interval 1 day - interval ? day) and 
                start < unix_timestamp(curdate() + interval 1 day - interval ? day) 
                order by id desc';
        return $this->findEntities($sql, [$user,$limitDays,$startDay],$limit, $offset);
    }

    public function findAllRunning($user, $limit = 100, $offset = 0){
        $sql = 'SELECT * FROM `*PREFIX*timetracker_work_interval` where user_uid = ? and running = 1 order by id desc';
        return $this->findEntities($sql, [$user],$limit, $offset);
    }

    public function stopAllRunning($user, $limit = 100, $offset = 0){
        $sql = 'update wi FROM `*PREFIX*timetracker_work_interval` wi set wi.running = 0 where user_uid = ? and running = 1';
        return $this->findEntities($sql, [$user],$limit, $offset);
    }

}