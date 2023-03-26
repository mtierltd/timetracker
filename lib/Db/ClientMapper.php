<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

class ClientMapper extends CompatibleMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timetracker_client');
    }


    public function findByName($name) {
        $sql = 'SELECT * FROM `*PREFIX*timetracker_client` ' .
            'WHERE upper(`name`) = ?';

            try {
                $e = $this->findEntity($sql, [strtoupper($name)]);
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
        $sql = 'SELECT * FROM `*PREFIX*timetracker_client` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }

    public function findAll($user){
        $sql = 'SELECT tc.* FROM `*PREFIX*timetracker_client` tc left join `*PREFIX*timetracker_user_to_client` uc on uc.client_id = tc.id where uc.user_uid = ? order by tc.name asc';
        return $this->findEntities($sql, [$user]);
    }

    public function searchByName($user, $name){
        $name = strtoupper($name);
        $sql = 'SELECT tc.* FROM `*PREFIX*timetracker_client` tc left join `*PREFIX*timetracker_user_to_client` uc on uc.client_id = tc.id where uc.user_uid = ? and upper(tc.name) LIKE ? order by tc.name asc';
        return $this->findEntities($sql, [$user, "%" . $name . "%"]);
    }
}
