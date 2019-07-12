<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class Timeline extends Entity {

    public $id;
    public $status;
    public $userUid;
    public $group1;
    public $group2;
    public $timeGroup;
    public $filterProjects;
    public $filterClients;
    public $timeInterval;
    public $totalDuration;
    public $createdAt;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('status', 'string');
        $this->addType('userUid', 'string');
        $this->addType('group1', 'string');
        $this->addType('group2', 'string');
        $this->addType('timeGroup', 'string');
        $this->addType('filterProjects', 'string');
        $this->addType('filterClients', 'string');
        $this->addType('timeInterval', 'string');
        $this->addType('totalDuration', 'string');
        $this->addType('createdAt', 'integer');
    }
}