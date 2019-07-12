<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class UserToClientMapper extends Mapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_user_to_client');
    }


    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_user_to_client` ' .
            'WHERE `id` = ?';
            
            try {
                $e = $this->findEntity($sql, [$id]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }

    public function findAllForUser($uid) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_user_to_client` ' .
            'WHERE `user_uid` = ?';
            
            try {
                $e = $this->findEntities($sql, [$uid]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }
    
    public function findForUserAndClient($uid, $client) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_user_to_client` ' .
            'WHERE `user_uid` = ? and client_id = ?';
            
            try {
                $e = $this->findEntity($sql, [$uid, $client->id]);
                return $e;
            } catch (\OCP\AppFramework\Db\DoesNotExistException $e){
                return null;
            }
        
    }

    
   
}