<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class Tag extends Entity {

    public $id;
    public $name;
    public $createdAt;
    public $userUid;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('name', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('userUid', 'string');
    }
}