<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class UserToProject extends Entity {

    public $id;
    public $userUid;
    public $projectId;
    public $admin;
    public $access;
    public $createdAt;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('userUid', 'string');
        $this->addType('projectId', 'integer');
        $this->addType('admin', 'integer');
        $this->addType('access', 'integer');
        $this->addType('createdAt', 'integer');

    }
}