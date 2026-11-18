<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setName(string $name)
 * @method void setProjectId(?int $projectId)
 * @method void setUserUid(string $userUid)
 * @method void setStart(int $start)
 * @method void setDuration(?int $duration)
 * @method void setRunning(int $running)
 * @method void setDetails(?string $details)
 * @method void setCost(?int $cost)
 */
class WorkInterval extends Entity {

    public $id;
    public $name;
    public $details;
    public $projectId;
    public $userUid;
    public $start;
    public $duration;
    public $running;
    public $cost;


    public function __construct() {
        // add types in constructor

        $this->addType('id', 'integer');
        $this->addType('name', 'string');
        $this->addType('details', 'string');
        $this->addType('projectId', 'integer');
        $this->addType('userUid', 'string');
        $this->addType('start', 'integer');
        $this->addType('duration', 'integer');
        $this->addType('running', 'integer');
        $this->addType('cost', 'integer');
    }
}
