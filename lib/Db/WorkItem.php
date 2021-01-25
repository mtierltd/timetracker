<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class WorkItem extends Entity {

    public $id;
    public $name;
    public $projectId;
    public $tagId;
    public $totalDuration;
    public $createdAt;
    public $userUid;


    public function __construct() {
        // add types in constructor
        $this->addType('id', 'integer');
        $this->addType('name', 'string');
        $this->addType('projectId', 'integer');
        $this->addType('tagId', 'integer');
        $this->addType('totalDuration', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('userUid', 'string');
    }
}