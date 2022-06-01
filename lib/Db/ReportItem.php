<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class ReportItem extends Entity {

    public $id;
    public $name;
    public $details;
    public $projectId;
    public $userUid;
    public $time;
    public $ftime;
    public $totalDuration;
    public $project;
    public $clientId;
    public $client;
    public $cost;



    public function __construct() {
        // add types in constructor

        $this->addType('id', 'integer');
        $this->addType('name', 'string');
        $this->addType('details', 'string');
        $this->addType('projectId', 'integer');
        $this->addType('userUid', 'string');
        $this->addType('time', 'string');
        $this->addType('ftime', 'string');
        $this->addType('totalDuration', 'integer');
        $this->addType('project', 'string');
        $this->addType('cost', 'integer');
    }
}
