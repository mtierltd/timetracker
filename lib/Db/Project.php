<?php
// db/author.php
namespace  OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

class Project extends Entity {

    public $id;
    public $name;
    public $color;
    public $clientId;
    public $createdByUserUid;
    public $createdAt;
    public $locked;
    public $archived;


    public function __construct() {
        // add types in constructor
        
        $this->addType('id', 'integer');
        $this->addType('name', 'string');
        $this->addType('color', 'string');
        $this->addType('clientId', 'integer');
        $this->addType('createdByUserUid', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('locked', 'integer');
        $this->addType('archived', 'integer');
    }
}