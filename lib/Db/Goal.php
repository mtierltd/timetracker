<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setDays(?string $days)
 */
class Goal extends Entity {

    public $id;
    public $userUid;
    public $projectId;
    public $projectName;
    public $hours;
    public $interval;
    public $createdAt;
    /** weekdays the goal counts on: 7 characters 0/1, Monday first; null: Monday to Friday */
    public $days;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('userUid', 'string');
        $this->addType('projectId', 'integer');
        $this->addType('projectName', 'string');
        $this->addType('hours', 'integer');
        $this->addType('interval', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('days', 'string');
    }
}