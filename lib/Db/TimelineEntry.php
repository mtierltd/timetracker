<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class TimelineEntry extends Entity {

    public $id;
    public $timelineId;
    public $userUid;
    public $name;
    public $projectName;
    public $clientName;
    public $timeInterval;
    public $totalDuration;
    public $createdAt;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('timelineId', 'string');
        $this->addType('userUid', 'string');
        $this->addType('name', 'string');
        $this->addType('projectName', 'string');
        $this->addType('clientName', 'string');
        $this->addType('timeInterval', 'string');
        $this->addType('totalDuration', 'string');
        $this->addType('createdAt', 'integer');
    }
}