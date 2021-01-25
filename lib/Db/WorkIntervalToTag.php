<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class WorkIntervalToTag extends Entity {

    public $id;
    public $workIntervalId;
    public $tagId;
    public $createdAt;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('workIntervalId', 'integer');
        $this->addType('tagId', 'integer');
        $this->addType('createdAt', 'integer');

    }
}