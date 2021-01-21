<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class Goal extends Entity {

    public $id;
    public $userUid;
    public $projectId;
    public $projectName;
    public $hours;
    public $interval;
    public $createdAt;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('userUid', 'string');
        $this->addType('projectId', 'integer');
        $this->addType('projectName', 'string');
        $this->addType('hours', 'integer');
        $this->addType('interval', 'string');
        $this->addType('createdAt', 'integer');
    }
}